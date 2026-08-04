#!/usr/bin/env python3
"""
Skylogs local HA lab — interactive / automated flow tester.

Default: pause after each step (press Enter to continue).
  python ha/test_flow.py
  python ha/test_flow.py --auto
  python ha/test_flow.py --from 4
  python ha/test_flow.py --only 5
  python ha/test_flow.py --list

Prereq: cluster up via .\\ha\\up.ps1
"""

from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from typing import Any, Callable

# ---------------------------------------------------------------------------
# Lab constants
# ---------------------------------------------------------------------------

HA_SECRET = "changeme-ha-secret"
HA_SECRET_HEADER = "X-Skylogs-HA-Secret"
ADMIN_USER = "admin"
ADMIN_PASS = "123456"

NODES = {
    "node1": {"nginx": "http://127.0.0.1:8083", "raft": "http://127.0.0.1:8801", "raft_ctr": "skylogs_raft-1"},
    "node2": {"nginx": "http://127.0.0.1:8183", "raft": "http://127.0.0.1:8802", "raft_ctr": "skylogs_raft-2"},
    "node3": {"nginx": "http://127.0.0.1:8283", "raft": "http://127.0.0.1:8803", "raft_ctr": "skylogs_raft-3"},
}

CONFIG_SYNC_TIMEOUT = 60.0
CONFIG_SYNC_POLL = 2.0
STATE_SYNC_TIMEOUT = 20.0
STATE_SYNC_POLL = 1.0
FAILOVER_TIMEOUT = 45.0


# ---------------------------------------------------------------------------
# HTTP helpers
# ---------------------------------------------------------------------------

class HttpError(RuntimeError):
    def __init__(self, status: int, body: str, url: str):
        self.status = status
        self.body = body
        self.url = url
        super().__init__(f"HTTP {status} {url}: {body[:400]}")


def http(
    method: str,
    url: str,
    *,
    headers: dict[str, str] | None = None,
    body: Any = None,
    timeout: float = 30.0,
) -> tuple[int, Any]:
    data = None
    req_headers = {"Accept": "application/json", **(headers or {})}
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        req_headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=req_headers, method=method.upper())
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            status = resp.getcode()
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8", errors="replace")
        raise HttpError(e.code, raw, url) from e
    except urllib.error.URLError as e:
        raise RuntimeError(f"request failed {url}: {e}") from e

    if not raw:
        return status, None
    try:
        return status, json.loads(raw)
    except json.JSONDecodeError:
        return status, raw


def ok(status: int) -> bool:
    return 200 <= status < 300


# ---------------------------------------------------------------------------
# Context
# ---------------------------------------------------------------------------

@dataclass
class Ctx:
    pause: bool = True
    suffix: str = field(default_factory=lambda: time.strftime("%H%M%S"))
    tokens: dict[str, str] = field(default_factory=dict)
    leader_node: str | None = None
    admin_id: str | None = None
    # created resources (leader ids)
    datasource_id: str | None = None
    user_id: str | None = None
    team_id: str | None = None
    endpoint_id: str | None = None
    alert_rule_id: str | None = None
    api_token: str | None = None
    alert_name: str = ""
    instance: str = "ha-lab-instance-1"
    failures: list[str] = field(default_factory=list)

    def tag(self, base: str) -> str:
        return f"{base}-{self.suffix}"


# ---------------------------------------------------------------------------
# Cluster helpers
# ---------------------------------------------------------------------------

def raft_status(node: str) -> dict[str, Any]:
    _, body = http("GET", f"{NODES[node]['raft']}/status", timeout=5)
    if not isinstance(body, dict):
        raise RuntimeError(f"bad raft status from {node}: {body}")
    return body


def discover_leader() -> str:
    leaders = []
    for node in NODES:
        try:
            st = raft_status(node)
        except Exception as e:
            print(f"  ! {node} raft unreachable: {e}")
            continue
        print(f"  {node}: is_leader={st.get('is_leader')} state={st.get('state')} leader={st.get('leader')}")
        if st.get("is_leader") is True:
            leaders.append(node)
    if len(leaders) != 1:
        raise RuntimeError(f"expected exactly one raft leader, got {leaders}")
    return leaders[0]


def followers(leader: str) -> list[str]:
    return [n for n in NODES if n != leader]


def nginx(node: str) -> str:
    return NODES[node]["nginx"]


def auth_header(token: str) -> dict[str, str]:
    return {"Authorization": f"Bearer {token}"}


def ha_headers() -> dict[str, str]:
    return {HA_SECRET_HEADER: HA_SECRET}


def login(node: str) -> str:
    status, body = http(
        "POST",
        f"{nginx(node)}/api/v1/auth/login",
        body={"username": ADMIN_USER, "password": ADMIN_PASS},
    )
    if not ok(status) or not isinstance(body, dict):
        raise RuntimeError(f"login failed on {node}: {status} {body}")
    token = body.get("accessToken") or body.get("access_token")
    if not token:
        raise RuntimeError(f"no access token on {node}: {body}")
    return str(token)


def me(node: str, token: str) -> dict[str, Any]:
    status, body = http("POST", f"{nginx(node)}/api/v1/auth/me", headers=auth_header(token))
    if not ok(status) or not isinstance(body, dict):
        raise RuntimeError(f"/auth/me failed on {node}: {status} {body}")
    return body


def wait_until(
    desc: str,
    pred: Callable[[], bool],
    *,
    timeout: float,
    interval: float,
) -> None:
    deadline = time.time() + timeout
    last_err: Exception | None = None
    while time.time() < deadline:
        try:
            if pred():
                print(f"  OK  {desc} ({timeout - (deadline - time.time()):.1f}s)")
                return
        except Exception as e:  # noqa: BLE001 — surface last error on timeout
            last_err = e
        time.sleep(interval)
    extra = f" last_error={last_err}" if last_err else ""
    raise TimeoutError(f"timeout waiting for: {desc}{extra}")


def force_config_sync_on_followers(ctx: Ctx) -> None:
    """Kick follower sync immediately instead of waiting for the 30s schedule."""
    assert ctx.leader_node
    for f in followers(ctx.leader_node):
        ctr = f"skylogs_back-{f[-1]}"  # node1 -> skylogs_back-1
        print(f"  forcing ha:config-sync on {ctr}")
        subprocess.run(
            ["docker", "exec", ctr, "php", "artisan", "ha:config-sync"],
            check=False,
            capture_output=True,
            text=True,
        )


def force_history_sync_on_followers(ctx: Ctx) -> None:
    assert ctx.leader_node
    for f in followers(ctx.leader_node):
        ctr = f"skylogs_back-{f[-1]}"
        subprocess.run(
            ["docker", "exec", ctr, "php", "artisan", "ha:history-sync"],
            check=False,
            capture_output=True,
            text=True,
        )


def docker(*args: str) -> None:
    cmd = ["docker", *args]
    print(f"  $ {' '.join(cmd)}")
    subprocess.run(cmd, check=True)


def wait_config(ctx: Ctx, getter, pred, desc: str) -> None:
    force_config_sync_on_followers(ctx)
    wait_followers_have(
        ctx,
        getter=getter,
        pred=pred,
        desc=desc,
        timeout=CONFIG_SYNC_TIMEOUT,
        interval=CONFIG_SYNC_POLL,
    )


# ---------------------------------------------------------------------------
# Resource helpers
# ---------------------------------------------------------------------------

def doc_id(doc: dict[str, Any] | None) -> str | None:
    if not doc:
        return None
    for key in ("_id", "id"):
        if doc.get(key):
            return str(doc[key])
    data = doc.get("data")
    if isinstance(data, dict):
        return doc_id(data)
    return None


def create_datasource(ctx: Ctx, node: str) -> str:
    name = ctx.tag("ha-ds")
    status, body = http(
        "POST",
        f"{nginx(node)}/api/v1/data-source",
        headers=auth_header(ctx.tokens[node]),
        body={"name": name, "type": "prometheus", "url": f"http://example.invalid/{ctx.suffix}"},
    )
    if not ok(status) or not isinstance(body, dict) or not body.get("status"):
        raise RuntimeError(f"create data-source failed: {status} {body}")
    ds_id = doc_id(body)
    if not ds_id:
        raise RuntimeError(f"create data-source: missing id in {body}")
    print(f"  created dataSource {name} id={ds_id} on {node}")
    return ds_id


def get_datasource(node: str, token: str, ds_id: str) -> dict[str, Any] | None:
    try:
        status, body = http("GET", f"{nginx(node)}/api/v1/data-source/{ds_id}", headers=auth_header(token))
    except HttpError as e:
        if e.status == 404:
            return None
        raise
    if status == 404:
        return None
    if not ok(status):
        raise RuntimeError(f"get data-source {ds_id} on {node}: {status} {body}")
    return body if isinstance(body, dict) else None


def update_datasource(ctx: Ctx, node: str, ds_id: str, name: str) -> None:
    status, body = http(
        "PUT",
        f"{nginx(node)}/api/v1/data-source/{ds_id}",
        headers=auth_header(ctx.tokens[node]),
        body={"name": name, "type": "prometheus", "url": f"http://example.invalid/{ctx.suffix}-upd"},
    )
    if not ok(status):
        raise RuntimeError(f"update data-source failed: {status} {body}")
    print(f"  updated dataSource -> {name}")


def delete_datasource(ctx: Ctx, node: str, ds_id: str) -> None:
    status, body = http(
        "DELETE",
        f"{nginx(node)}/api/v1/data-source/{ds_id}",
        headers=auth_header(ctx.tokens[node]),
    )
    if not ok(status):
        raise RuntimeError(f"delete data-source failed: {status} {body}")
    print(f"  deleted dataSource {ds_id}")


def create_user(ctx: Ctx, node: str) -> str:
    username = ctx.tag("halab").replace("-", "")[:20]
    status, body = http(
        "POST",
        f"{nginx(node)}/api/v1/user",
        headers=auth_header(ctx.tokens[node]),
        body={
            "username": username,
            "name": f"HA Lab {ctx.suffix}",
            "password": "123456",
            "confirmPassword": "123456",
            "role": "member",
        },
    )
    if not ok(status) or not isinstance(body, dict) or not body.get("status"):
        raise RuntimeError(f"create user failed: {status} {body}")
    user_id = doc_id(body)
    if not user_id:
        raise RuntimeError(f"create user: missing id in {body}")
    print(f"  created user {username} id={user_id} on {node}")
    return user_id


def get_user(node: str, token: str, user_id: str) -> dict[str, Any] | None:
    try:
        status, body = http("GET", f"{nginx(node)}/api/v1/user/{user_id}", headers=auth_header(token))
    except HttpError as e:
        if e.status == 404:
            return None
        raise
    if not ok(status):
        raise RuntimeError(f"get user on {node}: {status} {body}")
    return body if isinstance(body, dict) else None


def update_user(ctx: Ctx, node: str, user_id: str, name: str) -> None:
    # need current username for unique rule
    current = get_user(node, ctx.tokens[node], user_id)
    if not current:
        raise RuntimeError("user missing before update")
    status, body = http(
        "PUT",
        f"{nginx(node)}/api/v1/user/{user_id}",
        headers=auth_header(ctx.tokens[node]),
        body={"username": current["username"], "name": name, "role": "member"},
    )
    if not ok(status):
        raise RuntimeError(f"update user failed: {status} {body}")
    print(f"  updated user -> name={name}")


def delete_user(ctx: Ctx, node: str, user_id: str) -> None:
    status, body = http(
        "DELETE",
        f"{nginx(node)}/api/v1/user/{user_id}",
        headers=auth_header(ctx.tokens[node]),
    )
    if not ok(status):
        raise RuntimeError(f"delete user failed: {status} {body}")
    print(f"  deleted user {user_id}")


def create_team(ctx: Ctx, node: str) -> str:
    if not ctx.admin_id:
        raise RuntimeError("admin_id required for team")
    name = ctx.tag("ha-team")
    status, body = http(
        "POST",
        f"{nginx(node)}/api/v1/team",
        headers=auth_header(ctx.tokens[node]),
        body={
            "name": name,
            "ownerId": ctx.admin_id,
            "userIds": [ctx.admin_id],
            "description": "ha lab",
        },
    )
    if not ok(status) or not isinstance(body, dict) or not body.get("status"):
        raise RuntimeError(f"create team failed: {status} {body}")
    team_id = doc_id(body)
    if not team_id:
        raise RuntimeError(f"create team: missing id in {body}")
    print(f"  created team {name} id={team_id} on {node}")
    return team_id


def get_team(node: str, token: str, team_id: str) -> dict[str, Any] | None:
    try:
        status, body = http("GET", f"{nginx(node)}/api/v1/team/{team_id}", headers=auth_header(token))
    except HttpError as e:
        if e.status == 404:
            return None
        raise
    if not ok(status):
        raise RuntimeError(f"get team on {node}: {status} {body}")
    return body if isinstance(body, dict) else None


def update_team(ctx: Ctx, node: str, team_id: str, description: str) -> None:
    current = get_team(node, ctx.tokens[node], team_id)
    if not current:
        raise RuntimeError("team missing before update")
    status, body = http(
        "PUT",
        f"{nginx(node)}/api/v1/team/{team_id}",
        headers=auth_header(ctx.tokens[node]),
        body={
            "name": current.get("name"),
            "ownerId": current.get("ownerId") or ctx.admin_id,
            "userIds": current.get("userIds") or [ctx.admin_id],
            "description": description,
        },
    )
    if not ok(status):
        raise RuntimeError(f"update team failed: {status} {body}")
    print(f"  updated team description -> {description}")


def delete_team(ctx: Ctx, node: str, team_id: str) -> None:
    status, body = http(
        "DELETE",
        f"{nginx(node)}/api/v1/team/{team_id}",
        headers=auth_header(ctx.tokens[node]),
    )
    if not ok(status):
        raise RuntimeError(f"delete team failed: {status} {body}")
    print(f"  deleted team {team_id}")


def create_endpoint(ctx: Ctx, node: str) -> str:
    name = ctx.tag("ha-ep")
    status, body = http(
        "POST",
        f"{nginx(node)}/api/v1/endpoint",
        headers=auth_header(ctx.tokens[node]),
        body={
            "name": name,
            "type": "discord",
            "value": "https://discord.example/webhook/ha-lab",
            "isPublic": False,
            "accessUserIds": [],
            "accessTeamIds": [],
        },
    )
    if not ok(status) or not isinstance(body, dict) or not body.get("status"):
        raise RuntimeError(f"create endpoint failed: {status} {body}")
    ep_id = doc_id(body)
    if not ep_id:
        raise RuntimeError(f"create endpoint: missing id in {body}")
    print(f"  created endpoint {name} id={ep_id} on {node}")
    return ep_id


def get_endpoint(node: str, token: str, ep_id: str) -> dict[str, Any] | None:
    try:
        status, body = http("GET", f"{nginx(node)}/api/v1/endpoint/{ep_id}", headers=auth_header(token))
    except HttpError as e:
        if e.status == 404:
            return None
        raise
    if not ok(status):
        raise RuntimeError(f"get endpoint on {node}: {status} {body}")
    return body if isinstance(body, dict) else None


def update_endpoint(ctx: Ctx, node: str, ep_id: str, name: str) -> None:
    status, body = http(
        "PUT",
        f"{nginx(node)}/api/v1/endpoint/{ep_id}",
        headers=auth_header(ctx.tokens[node]),
        body={
            "name": name,
            "type": "discord",
            "value": "https://discord.example/webhook/ha-lab-updated",
            "isPublic": False,
            "accessUserIds": [],
            "accessTeamIds": [],
        },
    )
    if not ok(status):
        raise RuntimeError(f"update endpoint failed: {status} {body}")
    print(f"  updated endpoint -> {name}")


def delete_endpoint(ctx: Ctx, node: str, ep_id: str) -> None:
    status, body = http(
        "DELETE",
        f"{nginx(node)}/api/v1/endpoint/{ep_id}",
        headers=auth_header(ctx.tokens[node]),
    )
    if not ok(status):
        raise RuntimeError(f"delete endpoint failed: {status} {body}")
    print(f"  deleted endpoint {ep_id}")


def create_alert_rule(ctx: Ctx, node: str) -> str:
    ctx.alert_name = ctx.tag("ha-api-rule")
    status, body = http(
        "POST",
        f"{nginx(node)}/api/v1/alert-rule",
        headers=auth_header(ctx.tokens[node]),
        body={
            "name": ctx.alert_name,
            "type": "api",
            "enableAutoResolve": False,
            "endpoints": [],
            "accessUsers": [],
            "tags": [],
        },
    )
    if not ok(status) or not isinstance(body, dict) or not body.get("status"):
        raise RuntimeError(f"create alert-rule failed: {status} {body}")
    rule = find_alert_by_name(node, ctx.tokens[node], ctx.alert_name)
    if not rule:
        raise RuntimeError("alert-rule created but not found via list")
    rule_id = doc_id(rule)
    if not rule_id:
        raise RuntimeError(f"alert-rule missing id: {rule}")
    print(f"  created alertRule {ctx.alert_name} id={rule_id} on {node}")
    return rule_id


def find_alert_by_name(node: str, token: str, name: str) -> dict[str, Any] | None:
    # alertname is a Mongo regex; lab names have no special chars.
    q = urllib.parse.urlencode({"alertname": f"^{name}$", "perPage": "50"})
    status, body = http("GET", f"{nginx(node)}/api/v1/alert-rule?{q}", headers=auth_header(token))
    if not ok(status) or not isinstance(body, dict):
        raise RuntimeError(f"list alert-rule failed: {status} {body}")
    data = body.get("data") or []
    for item in data:
        if item.get("name") == name:
            return item
    return None


def raft_set(leader_node: str, key: str, value: dict[str, Any]) -> None:
    """Write through Raft leader HTTP /set so every node gets notify → /api/ha/apply."""
    status, body = http(
        "POST",
        f"{NODES[leader_node]['raft']}/set",
        body={"key": key, "value": value},
    )
    if not ok(status):
        raise RuntimeError(f"raft /set failed on {leader_node}: {status} {body}")
    print(f"  raft /set ok on {leader_node} key={key}")


def get_alert_rule(node: str, token: str, rule_id: str) -> dict[str, Any] | None:
    try:
        status, body = http("GET", f"{nginx(node)}/api/v1/alert-rule/{rule_id}", headers=auth_header(token))
    except HttpError as e:
        if e.status in (403, 404):
            return None
        raise
    if not ok(status):
        raise RuntimeError(f"get alert-rule on {node}: {status} {body}")
    return body if isinstance(body, dict) else None


def update_alert_rule(ctx: Ctx, node: str, rule_id: str, description: str) -> None:
    status, body = http(
        "PUT",
        f"{nginx(node)}/api/v1/alert-rule/{rule_id}",
        headers=auth_header(ctx.tokens[node]),
        body={
            "name": ctx.alert_name,
            "type": "api",
            "description": description,
            "enableAutoResolve": False,
            "showAcknowledgeBtn": False,
            "endpoints": [],
            "accessUsers": [],
            "tags": [],
        },
    )
    if not ok(status):
        raise RuntimeError(f"update alert-rule failed: {status} {body}")
    print(f"  updated alertRule description -> {description}")


def delete_alert_rule(ctx: Ctx, node: str, rule_id: str) -> None:
    status, body = http(
        "DELETE",
        f"{nginx(node)}/api/v1/alert-rule/{rule_id}",
        headers=auth_header(ctx.tokens[node]),
    )
    if not ok(status):
        raise RuntimeError(f"delete alert-rule failed: {status} {body}")
    print(f"  deleted alertRule {rule_id}")


def fire_alert(ctx: Ctx, node: str) -> None:
    assert ctx.api_token
    status, body = http(
        "POST",
        f"{nginx(node)}/api/v1/fire-alert",
        headers=auth_header(ctx.api_token),
        body={
            "instance": ctx.instance,
            "description": "ha lab fire",
            "summary": "critical from lab",
        },
    )
    if not ok(status):
        raise RuntimeError(f"fire-alert failed: {status} {body}")
    print(f"  fire-alert on {node}: {body}")


def resolve_alert_webhook(ctx: Ctx, node: str) -> None:
    assert ctx.api_token
    status, body = http(
        "POST",
        f"{nginx(node)}/api/v1/resolve-alert",
        headers=auth_header(ctx.api_token),
        body={"instance": ctx.instance, "description": "ha lab resolve"},
    )
    if not ok(status):
        raise RuntimeError(f"resolve-alert failed: {status} {body}")
    print(f"  resolve-alert on {node}: {body}")


def apply_state(
    node: str,
    *,
    rule_id: str,
    rule_name: str,
    state: str,
    version: int,
    instance: str,
    fire_count: int,
    instance_state: int,
) -> None:
    instance_id = hashlib.sha1(instance.encode("utf-8")).hexdigest()
    key = f"alert:{rule_id}:api:{instance_id}"
    now = int(time.time())
    value = {
        "key": key,
        "version": version,
        "nodeId": node,
        "timestamp": now,
        "alertRuleId": rule_id,
        "alertRuleName": rule_name,
        "type": "api",
        "instance": {"instance": instance},
        "state": state,
        "firedAt": now if state != "resolved" else None,
        "resolvedAt": now if state == "resolved" else None,
        "rule": {
            "state": state,
            "fireCount": fire_count,
            "notifyAt": now,
            "acknowledgedBy": None,
        },
        "extra": {
            "instanceState": instance_state,
            "description": f"lab {state}",
        },
    }
    status, body = http(
        "POST",
        f"{nginx(node)}/api/ha/apply",
        headers=ha_headers(),
        body={"key": key, "value": value},
    )
    if not ok(status):
        raise RuntimeError(f"ha apply failed on {node}: {status} {body}")
    print(f"  ha/apply {state} v{version} on {node}: {body}")


def alert_state_on(node: str, token: str, rule_id: str) -> str | None:
    rule = get_alert_rule(node, token, rule_id)
    if not rule:
        return None
    return rule.get("statusLabel") or rule.get("state")


def wait_followers_have(
    ctx: Ctx,
    *,
    getter: Callable[[str], Any],
    pred: Callable[[Any], bool],
    desc: str,
    timeout: float = CONFIG_SYNC_TIMEOUT,
    interval: float = CONFIG_SYNC_POLL,
) -> None:
    assert ctx.leader_node
    for f in followers(ctx.leader_node):
        wait_until(
            f"{desc} on {f}",
            lambda f=f: pred(getter(f)),
            timeout=timeout,
            interval=interval,
        )


# ---------------------------------------------------------------------------
# Steps
# ---------------------------------------------------------------------------

def api_reachable(base: str, *, timeout: float = 5.0) -> int:
    """Any response from the API stack counts — / may 500 on session bugs."""
    url = f"{base.rstrip('/')}/api/v1/alert-rule"
    req = urllib.request.Request(url, headers={"Accept": "application/json"}, method="GET")
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return resp.getcode()
    except urllib.error.HTTPError as e:
        return e.code
    except urllib.error.URLError as e:
        raise RuntimeError(f"API unreachable {url}: {e}") from e


def step_cluster_health(ctx: Ctx) -> None:
    print("Check raft + API on all nodes.")
    ctx.leader_node = discover_leader()
    print(f"  leader = {ctx.leader_node}")
    for node, meta in NODES.items():
        code = api_reachable(meta["nginx"])
        # 401 without JWT means nginx → php-fpm is fine
        print(f"  api {node} HTTP {code}")
        if code not in (200, 401, 403):
            raise RuntimeError(f"api {node} not healthy (HTTP {code})")


def step_peer_reachability(ctx: Ctx) -> None:
    """Simulate production peer map: host.docker.internal hairpin via published ports."""
    print("Verify HA peer API from inside a back container (host.docker.internal).")
    for port in (8083, 8183, 8283):
        r = subprocess.run(
            [
                "docker", "exec", "skylogs_back-1", "sh", "-c",
                "curl -s -o /dev/null -w '%{http_code}' --max-time 5 "
                f"-H 'Accept: application/json' "
                f"http://host.docker.internal:{port}/api/v1/alert-rule",
            ],
            capture_output=True,
            text=True,
        )
        code = (r.stdout or "").strip()
        print(f"  back-1 -> host.docker.internal:{port}/api/v1/alert-rule => {code or r.stderr.strip()}")
        if code not in ("200", "401", "403"):
            raise RuntimeError(f"peer reachability failed for :{port} (HTTP {code})")


def step_auth(ctx: Ctx) -> None:
    print(f"Login as {ADMIN_USER} on every node.")
    for node in NODES:
        ctx.tokens[node] = login(node)
        print(f"  {node}: token ok")
    assert ctx.leader_node
    profile = me(ctx.leader_node, ctx.tokens[ctx.leader_node])
    ctx.admin_id = doc_id(profile)
    if not ctx.admin_id:
        raise RuntimeError(f"admin id missing from /me: {profile}")
    print(f"  admin_id={ctx.admin_id}")


def step_config_crud(ctx: Ctx) -> None:
    """Create / update / delete config-synced resources on leader; wait for followers."""
    assert ctx.leader_node
    leader = ctx.leader_node
    print(f"Config CRUD on leader={leader}; force ha:config-sync on followers after each change.")

    # --- dataSource ---
    print("\n  [dataSource] create")
    ctx.datasource_id = create_datasource(ctx, leader)
    wait_config(
        ctx,
        lambda n: get_datasource(n, ctx.tokens[n], ctx.datasource_id),  # type: ignore[arg-type]
        lambda doc: doc is not None,
        f"dataSource {ctx.datasource_id} present",
    )
    new_ds_name = ctx.tag("ha-ds-upd")
    print("  [dataSource] update")
    update_datasource(ctx, leader, ctx.datasource_id, new_ds_name)
    wait_config(
        ctx,
        lambda n: get_datasource(n, ctx.tokens[n], ctx.datasource_id),  # type: ignore[arg-type]
        lambda doc: bool(doc) and doc.get("name") == new_ds_name,
        f"dataSource name={new_ds_name}",
    )

    # --- user ---
    print("\n  [user] create")
    ctx.user_id = create_user(ctx, leader)
    wait_config(
        ctx,
        lambda n: get_user(n, ctx.tokens[n], ctx.user_id),  # type: ignore[arg-type]
        lambda doc: doc is not None,
        f"user {ctx.user_id} present",
    )
    new_user_name = f"HA Lab Updated {ctx.suffix}"
    print("  [user] update")
    update_user(ctx, leader, ctx.user_id, new_user_name)
    wait_config(
        ctx,
        lambda n: get_user(n, ctx.tokens[n], ctx.user_id),  # type: ignore[arg-type]
        lambda doc: bool(doc) and doc.get("name") == new_user_name,
        f"user name={new_user_name}",
    )

    # --- team ---
    print("\n  [team] create")
    ctx.team_id = create_team(ctx, leader)
    wait_config(
        ctx,
        lambda n: get_team(n, ctx.tokens[n], ctx.team_id),  # type: ignore[arg-type]
        lambda doc: doc is not None,
        f"team {ctx.team_id} present",
    )
    new_team_desc = f"updated-{ctx.suffix}"
    print("  [team] update")
    update_team(ctx, leader, ctx.team_id, new_team_desc)
    wait_config(
        ctx,
        lambda n: get_team(n, ctx.tokens[n], ctx.team_id),  # type: ignore[arg-type]
        lambda doc: bool(doc) and doc.get("description") == new_team_desc,
        f"team description={new_team_desc}",
    )

    # --- endpoint ---
    print("\n  [endpoint] create")
    ctx.endpoint_id = create_endpoint(ctx, leader)
    wait_config(
        ctx,
        lambda n: get_endpoint(n, ctx.tokens[n], ctx.endpoint_id),  # type: ignore[arg-type]
        lambda doc: doc is not None,
        f"endpoint {ctx.endpoint_id} present",
    )
    new_ep_name = ctx.tag("ha-ep-upd")
    print("  [endpoint] update")
    update_endpoint(ctx, leader, ctx.endpoint_id, new_ep_name)
    wait_config(
        ctx,
        lambda n: get_endpoint(n, ctx.tokens[n], ctx.endpoint_id),  # type: ignore[arg-type]
        lambda doc: bool(doc) and doc.get("name") == new_ep_name,
        f"endpoint name={new_ep_name}",
    )

    # --- alertRule ---
    print("\n  [alertRule] create")
    ctx.alert_rule_id = create_alert_rule(ctx, leader)
    wait_config(
        ctx,
        lambda n: get_alert_rule(n, ctx.tokens[n], ctx.alert_rule_id),  # type: ignore[arg-type]
        lambda doc: doc is not None,
        f"alertRule {ctx.alert_rule_id} present",
    )
    new_desc = f"desc-{ctx.suffix}"
    print("  [alertRule] update")
    update_alert_rule(ctx, leader, ctx.alert_rule_id, new_desc)
    wait_config(
        ctx,
        lambda n: get_alert_rule(n, ctx.tokens[n], ctx.alert_rule_id),  # type: ignore[arg-type]
        lambda doc: bool(doc) and (doc.get("description") or "") == new_desc,
        f"alertRule description={new_desc}",
    )

    show = get_alert_rule(leader, ctx.tokens[leader], ctx.alert_rule_id)
    assert show
    ctx.api_token = show.get("apiToken")
    if not ctx.api_token:
        raise RuntimeError("apiToken missing on leader alert-rule show (need owner access)")
    print(f"  apiToken captured ({len(ctx.api_token)} chars)")

    # Deletes (keep alertRule for state tests — deleted in later step)
    print("\n  [dataSource/user/team/endpoint] delete")
    delete_datasource(ctx, leader, ctx.datasource_id)
    wait_config(
        ctx,
        lambda n: get_datasource(n, ctx.tokens[n], ctx.datasource_id),  # type: ignore[arg-type]
        lambda doc: doc is None,
        "dataSource deleted on followers",
    )
    delete_user(ctx, leader, ctx.user_id)
    wait_config(
        ctx,
        lambda n: get_user(n, ctx.tokens[n], ctx.user_id),  # type: ignore[arg-type]
        lambda doc: doc is None,
        "user deleted on followers",
    )
    delete_team(ctx, leader, ctx.team_id)
    wait_config(
        ctx,
        lambda n: get_team(n, ctx.tokens[n], ctx.team_id),  # type: ignore[arg-type]
        lambda doc: doc is None,
        "team deleted on followers",
    )
    delete_endpoint(ctx, leader, ctx.endpoint_id)
    wait_config(
        ctx,
        lambda n: get_endpoint(n, ctx.tokens[n], ctx.endpoint_id),  # type: ignore[arg-type]
        lambda doc: doc is None,
        "endpoint deleted on followers",
    )


def step_alert_state_fire(ctx: Ctx) -> None:
    assert ctx.leader_node and ctx.alert_rule_id and ctx.api_token
    leader = ctx.leader_node
    print(f"Fire API alert on leader={leader}; expect critical on followers via Raft notify.")
    fire_alert(ctx, leader)

    def is_critical(n: str) -> bool:
        st = alert_state_on(n, ctx.tokens[n], ctx.alert_rule_id)  # type: ignore[arg-type]
        print(f"    {n} state={st}")
        return st in ("critical", "triggered")

    wait_until("leader shows critical", lambda: is_critical(leader), timeout=STATE_SYNC_TIMEOUT, interval=STATE_SYNC_POLL)
    for f in followers(leader):
        wait_until(
            f"follower {f} shows critical",
            lambda f=f: is_critical(f),
            timeout=STATE_SYNC_TIMEOUT,
            interval=STATE_SYNC_POLL,
        )


def step_alert_state_warning(ctx: Ctx) -> None:
    """API webhooks only fire critical/resolved; warning goes through Raft /set → notify."""
    assert ctx.leader_node and ctx.alert_rule_id
    leader = ctx.leader_node
    print("Publish warning via Raft /set on leader (fans out to every /api/ha/apply).")
    instance_id = hashlib.sha1(ctx.instance.encode("utf-8")).hexdigest()
    key = f"alert:{ctx.alert_rule_id}:api:{instance_id}"
    now = int(time.time())
    value = {
        "key": key,
        "version": 20,
        "nodeId": leader,
        "timestamp": now,
        "alertRuleId": ctx.alert_rule_id,
        "alertRuleName": ctx.alert_name,
        "type": "api",
        "instance": {"instance": ctx.instance},
        "state": "warning",
        "firedAt": now,
        "resolvedAt": None,
        "rule": {
            "state": "warning",
            "fireCount": 1,
            "notifyAt": now,
            "acknowledgedBy": None,
        },
        "extra": {
            "instanceState": 2,
            "description": "lab warning",
        },
    }
    raft_set(leader, key, value)

    def is_warning(n: str) -> bool:
        st = alert_state_on(n, ctx.tokens[n], ctx.alert_rule_id)  # type: ignore[arg-type]
        print(f"    {n} state={st}")
        return st == "warning"

    for n in NODES:
        wait_until(
            f"{n} shows warning",
            lambda n=n: is_warning(n),
            timeout=STATE_SYNC_TIMEOUT,
            interval=STATE_SYNC_POLL,
        )


def step_alert_state_resolve(ctx: Ctx) -> None:
    assert ctx.leader_node and ctx.alert_rule_id and ctx.api_token
    leader = ctx.leader_node
    print(f"Resolve API alert on leader={leader}; expect resolved on followers.")
    resolve_alert_webhook(ctx, leader)

    def is_resolved(n: str) -> bool:
        st = alert_state_on(n, ctx.tokens[n], ctx.alert_rule_id)  # type: ignore[arg-type]
        print(f"    {n} state={st}")
        return st in ("resolved", None, "")

    wait_until("leader shows resolved", lambda: is_resolved(leader), timeout=STATE_SYNC_TIMEOUT, interval=STATE_SYNC_POLL)
    for f in followers(leader):
        wait_until(
            f"follower {f} shows resolved",
            lambda f=f: is_resolved(f),
            timeout=STATE_SYNC_TIMEOUT,
            interval=STATE_SYNC_POLL,
        )


def step_alert_state_fire_via_raft_path(ctx: Ctx) -> None:
    """Second fire through real webhook so Raft fan-out is exercised after warning apply."""
    assert ctx.leader_node and ctx.alert_rule_id and ctx.api_token
    leader = ctx.leader_node
    print("Re-fire via webhook to exercise Raft publish -> notify -> apply fan-out.")
    fire_alert(ctx, leader)

    def is_critical(n: str) -> bool:
        st = alert_state_on(n, ctx.tokens[n], ctx.alert_rule_id)  # type: ignore[arg-type]
        print(f"    {n} state={st}")
        return st in ("critical", "triggered")

    for n in NODES:
        wait_until(
            f"{n} critical after raft fire",
            lambda n=n: is_critical(n),
            timeout=STATE_SYNC_TIMEOUT,
            interval=STATE_SYNC_POLL,
        )


def step_failover(ctx: Ctx) -> None:
    assert ctx.leader_node
    old = ctx.leader_node
    ctr = NODES[old]["raft_ctr"]
    print(f"Crash leader raft ({ctr}) with docker kill; wait for new leader.")
    docker("kill", ctr)
    deadline = time.time() + FAILOVER_TIMEOUT
    new_leader = None
    while time.time() < deadline:
        try:
            new_leader = discover_leader()
            if new_leader != old:
                break
        except Exception as e:  # noqa: BLE001
            print(f"  waiting election... {e}")
        time.sleep(2)
    if not new_leader or new_leader == old:
        raise RuntimeError("failover did not elect a different leader")
    ctx.leader_node = new_leader
    print(f"  new leader = {new_leader}")
    # refresh tokens (still valid usually)
    for node in NODES:
        if node == old:
            continue
        try:
            ctx.tokens[node] = login(node)
        except Exception as e:  # noqa: BLE001
            print(f"  re-login {node}: {e}")


def step_post_failover_config(ctx: Ctx) -> None:
    assert ctx.leader_node
    leader = ctx.leader_node
    print(f"Create a dataSource on new leader={leader}; expect remaining followers sync.")
    ds_id = create_datasource(ctx, leader)
    wait_config(
        ctx,
        lambda n: get_datasource(n, ctx.tokens[n], ds_id),
        lambda doc: doc is not None,
        f"dataSource {ds_id} present",
    )
    delete_datasource(ctx, leader, ds_id)
    wait_config(
        ctx,
        lambda n: get_datasource(n, ctx.tokens[n], ds_id),
        lambda doc: doc is None,
        f"dataSource {ds_id} deleted",
    )


def step_restore_old_leader(ctx: Ctx) -> None:
    print("Start killed raft container back; it should rejoin from /data.")
    for node, meta in NODES.items():
        r = subprocess.run(
            ["docker", "inspect", "-f", "{{.State.Running}}", meta["raft_ctr"]],
            capture_output=True,
            text=True,
        )
        running = (r.stdout or "").strip().lower() == "true"
        if not running:
            docker("start", meta["raft_ctr"])
            print(f"  started {meta['raft_ctr']}")
    time.sleep(5)
    leader = discover_leader()
    ctx.leader_node = leader
    print(f"  cluster leader after restore = {leader}")
    for node in NODES:
        st = raft_status(node)
        print(f"  {node}: {st}")


def step_cleanup_alert_rule(ctx: Ctx) -> None:
    if not ctx.alert_rule_id or not ctx.leader_node:
        print("  nothing to clean")
        return
    leader = ctx.leader_node
    print(f"Delete lab alertRule on leader={leader}")
    try:
        delete_alert_rule(ctx, leader, ctx.alert_rule_id)
        wait_config(
            ctx,
            lambda n: get_alert_rule(n, ctx.tokens[n], ctx.alert_rule_id),  # type: ignore[arg-type]
            lambda doc: doc is None,
            "alertRule deleted on followers",
        )
    except Exception as e:  # noqa: BLE001
        print(f"  cleanup warning: {e}")


STEPS: list[tuple[str, Callable[[Ctx], None]]] = [
    ("cluster_health", step_cluster_health),
    ("peer_reachability", step_peer_reachability),
    ("auth", step_auth),
    ("config_crud_replicate", step_config_crud),
    ("alert_state_fire", step_alert_state_fire),
    ("alert_state_warning", step_alert_state_warning),
    ("alert_state_resolve", step_alert_state_resolve),
    ("alert_state_fire_raft_again", step_alert_state_fire_via_raft_path),
    ("failover_kill_leader", step_failover),
    ("post_failover_config", step_post_failover_config),
    ("restore_old_leader", step_restore_old_leader),
    ("cleanup", step_cleanup_alert_rule),
]


# ---------------------------------------------------------------------------
# Runner
# ---------------------------------------------------------------------------

def pause(ctx: Ctx, title: str) -> None:
    if not ctx.pause:
        return
    try:
        input(f"\n>>> [{title}] check status, then press Enter to continue...")
    except EOFError:
        print("\n(no stdin — continuing)")


def run(ctx: Ctx, selected: list[tuple[str, Callable[[Ctx], None]]]) -> int:
    print("=" * 60)
    print("Skylogs HA lab flow")
    print(f"mode={'pause' if ctx.pause else 'auto'}  suffix={ctx.suffix}")
    print("=" * 60)

    failed = False
    for i, (name, fn) in enumerate(selected, start=1):
        print(f"\n{'=' * 60}\nSTEP {i}/{len(selected)}: {name}\n{'=' * 60}")
        try:
            fn(ctx)
            print(f"PASS  {name}")
        except Exception as e:  # noqa: BLE001
            failed = True
            ctx.failures.append(f"{name}: {e}")
            print(f"FAIL  {name}: {e}")
            if not ctx.pause:
                break
            print("  (pause mode: fix/inspect, Enter to try next step)")
        pause(ctx, name)

    print("\n" + "=" * 60)
    if ctx.failures:
        print("FAILURES:")
        for f in ctx.failures:
            print(f"  - {f}")
    else:
        print("ALL SELECTED STEPS PASSED")
    print("=" * 60)
    return 1 if failed else 0


def parse_args(argv: list[str]) -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Skylogs HA lab interactive/automated flow")
    p.add_argument("--auto", action="store_true", help="no Enter pauses; fail fast")
    p.add_argument("--from", dest="from_step", type=int, default=1, help="1-based step index to start")
    p.add_argument("--only", type=int, default=None, help="run only this 1-based step")
    p.add_argument("--list", action="store_true", help="list steps and exit")
    return p.parse_args(argv)


def main(argv: list[str]) -> int:
    # Windows consoles often default to a legacy code page; keep logs ASCII-safe.
    if hasattr(sys.stdout, "reconfigure"):
        try:
            sys.stdout.reconfigure(encoding="utf-8", errors="replace")
            sys.stderr.reconfigure(encoding="utf-8", errors="replace")
        except Exception:  # noqa: BLE001
            pass

    args = parse_args(argv)
    if args.list:
        for i, (name, _) in enumerate(STEPS, start=1):
            print(f"{i:2d}. {name}")
        return 0

    if args.only is not None:
        if not 1 <= args.only <= len(STEPS):
            print(f"--only out of range 1..{len(STEPS)}", file=sys.stderr)
            return 2
        selected = [STEPS[args.only - 1]]
    else:
        start = max(1, args.from_step)
        selected = STEPS[start - 1 :]

    ctx = Ctx(pause=not args.auto)
    # If starting mid-flow, still discover leader + auth when possible
    if selected[0][0] != "cluster_health":
        try:
            ctx.leader_node = discover_leader()
            for node in NODES:
                ctx.tokens[node] = login(node)
            profile = me(ctx.leader_node, ctx.tokens[ctx.leader_node])
            ctx.admin_id = doc_id(profile)
        except Exception as e:  # noqa: BLE001
            print(f"bootstrap for --from/--only: {e}")

    return run(ctx, selected)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
