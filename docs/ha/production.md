# Skylogs High Availability — Production Guide (DevOps)

This document is the production runbook for deploying and operating Skylogs **node HA**: a multi-node active/passive cluster where one Raft-elected leader evaluates alerts and notifies, and followers stay warm with replicated alert state and configuration.

It is **not** the main/agent multi-cluster feature (`CLUSTER_TYPE=main|agent`, `/api/cluster/*`). That is a separate product concern.

---

## 1. What HA does (and does not)

| Concern | How it works |
|--------|----------------|
| Leadership | HashiCorp Raft sidecar elects one leader |
| Alert runtime state | Raft KV log + `POST /api/ha/apply` on every node |
| Users, rules, endpoints, configs | Followers pull snapshots from the leader via `GET /api/ha/config-sync` |
| Evaluation & notifications | **Leader only** |
| MongoDB / Redis | **Independent per node** (not shared, not DB replicas) |
| Queues | Per-node Redis + Horizon; dedicated `ha` queue |

**When `HA_ENABLED=false`:** the node behaves as a single install — no leadership checks, no replication, all scheduled jobs run locally.

**Do not:**

- Share one MongoDB or Redis across HA nodes for this design
- Point Raft notify or HA peer URLs at a user-facing VIP
- Use graceful stop (`docker stop` / SIGTERM) when testing crash failover — SIGTERM removes the Raft member from the cluster

---

## 2. Architecture

```text
                    ┌─────────────────────────────────────┐
                    │         Optional user LB / VIP       │
                    │   (API traffic only — not HA peers)  │
                    └──────────────┬──────────────────────┘
           ┌───────────────────────┼───────────────────────┐
           ▼                       ▼                       ▼
    ┌─────────────┐         ┌─────────────┐         ┌─────────────┐
    │   Node 1    │         │   Node 2    │         │   Node 3    │
    │ nginx+API   │◄───────►│ nginx+API   │◄───────►│ nginx+API   │
    │ Horizon     │ config  │ Horizon     │ config  │ Horizon     │
    │ Mongo local │  sync   │ Mongo local │  sync   │ Mongo local │
    │ Redis local │         │ Redis local │         │ Redis local │
    │ Raft :7000  │◄───────►│ Raft :7000  │◄───────►│ Raft :7000  │
    │ Raft HTTP   │  Raft   │ Raft HTTP   │  Raft   │ Raft HTTP   │
    │   :8000     │   TCP   │   :8000     │   TCP   │   :8000     │
    └─────────────┘         └─────────────┘         └─────────────┘
           │                       │                       │
           └──────── notify ───────┴─────── notify ────────┘
                 POST /api/ha/apply on THIS node's nginx
```

### Two replication paths (do not merge them)

1. **Raft (hot / alert state)** — check documents and alert-rule fields `state`, `fireCount`, `notifyAt`, `acknowledgedBy`. Near real-time via Raft commit + local notify; repaired every minute by reconcile.
2. **Config sync (cold / configuration)** — users, roles, teams, endpoints, datasources, alert-rule *config* (excluding Raft-owned fields), silent rules, notification configs, etc. Followers pull every **30 seconds**.

They share `alertRules`; config sync deliberately excludes Raft-owned fields so the paths do not fight.

---

## 3. Topology requirements

| Component | Requirement |
|-----------|-------------|
| Node count | **Odd** (3 recommended). Each node is a full stack. |
| Raft sidecar | One per node. Raft TCP **7000** between peers; HTTP API **8000** (local / published as needed). |
| Backend + nginx | Per node. Raft `RAFT_NOTIFY_URL` must hit **this node's** nginx, never a VIP. |
| MongoDB | Independent instance (or dedicated DB) **per node**. |
| Redis | Independent instance **per node** (cache + queues). |
| Horizon | Must process queue `ha` (`supervisor-ha` in `config/horizon.php`). Scheduler cron runs from the Horizon image entrypoint. |
| Network | Stable Raft advertise addresses reachable peer-to-peer (fixed IPs or stable DNS). |
| Raft data volume | Persistent `/data` **per node**. Losing all volumes = new cluster. |
| Shared app storage | Not required for HA data paths. |
| DB replication | Not used by this HA design. |

### Reference local lab ports (`ha/`)

| | Node 1 | Node 2 | Node 3 |
|--|--------|--------|--------|
| API nginx (host) | 8083 | 8183 | 8283 |
| Raft HTTP (host) | 8801 | 8802 | 8803 |
| Mongo (host) | 27117 | 27118 | 27119 |
| Redis (host) | 6479 | 6480 | 6481 |
| Raft IP | 172.28.7.11 | 172.28.7.12 | 172.28.7.13 |

Production hosts/IPs will differ; keep the same *patterns* (unique `RAFT_ADV_ADDR`, complete `HA_PEER_URLS`, local notify).

---

## 4. Environment configuration

### 4.1 Backend / Horizon (every node)

| Variable | Required | Default | Purpose |
|----------|----------|---------|---------|
| `HA_ENABLED` | yes | `false` | Master switch. Set `true` on all HA nodes. |
| `HA_NODE_ID` | yes | `node-1` | Human node identity (e.g. `node-1`, `node-2`). |
| `HA_NODE_SECRET` | yes | empty | Shared secret for HA HTTP APIs. **Must match** `RAFT_NOTIFY_SECRET` on every raft. |
| `HA_PEER_URLS` | yes | empty | Map Raft advertise address → that node's backend base URL. |
| `HA_ALLOWED_CIDRS` | no | empty | Optional CIDR allowlist for HA endpoints. Empty = any IP (secret still required). |
| `HA_LEADER_CACHE_SECONDS` | no | `2` | Cache Raft `/status` answers. |
| `HA_STATE_RETENTION_DAYS` | no | `7` | Leader tombstones old resolved alert slots. |
| `HA_CONFIG_SYNC_ENABLED` | no | `true` | Follower config pull. |
| `HA_CONFIG_SYNC_CONNECT_TIMEOUT` | no | `2` | Seconds. |
| `HA_CONFIG_SYNC_TIMEOUT` | no | `30` | Seconds (first full snapshot can be large). |
| `RAFT_URL` | yes | `http://raft:8000` | **This node's** Raft HTTP API. |
| `RAFT_CONNECT_TIMEOUT` | no | `0.5` | |
| `RAFT_STATUS_TIMEOUT` | no | `1` | |
| `RAFT_SET_TIMEOUT` | no | `3` | |
| `RAFT_GET_TIMEOUT` | no | `3` | |
| `RAFT_RETRY_ATTEMPTS` | no | `2` | Total attempts (2 = one retry). |
| `RAFT_RETRY_SLEEP_MILLISECONDS` | no | `100` | |

Config file: `apps/backend/config/ha.php`. Example comments: `apps/backend/.env.example`. Shared lab values: `ha/node.env`.

#### `HA_PEER_URLS` format (critical)

Raft names the leader as a **Raft address** (`172.28.7.11:7000`), never as an HTTP URL. Followers resolve that to a backend URL only via this map:

```bash
HA_PEER_URLS=172.28.7.11=http://nginx-1:80,172.28.7.12=http://nginx-2:80,172.28.7.13=http://nginx-3:80
```

Rules:

- Key by that node's `RAFT_ADV_ADDR` (with or without `:7000`).
- Value = base URL of that node's **nginx/API** (reachable from other nodes).
- Use the **same complete map on every node**.
- Do not put a load-balancer VIP here.

### 4.2 Raft sidecar (every node)

| Variable | Required | Purpose |
|----------|----------|---------|
| `RAFT_NODE_ID` | yes | Unique id (`node1`, `node2`, …). |
| `RAFT_ADV_ADDR` | yes | Address **peers** use to reach this node (no port). Must be stable. |
| `RAFT_START_MODE` | bootstrap / join / empty | See bootstrap rules below. |
| `RAFT_NOTIFY_URL` | yes for HA | `http://<local-nginx>/api/ha/apply` |
| `RAFT_NOTIFY_SECRET` | yes | Same value as `HA_NODE_SECRET` |
| `RAFT_NOTIFY_HEADER` | yes | Must be `X-Skylogs-HA-Secret` |

Ports inside the container (fixed by Raft entrypoint): Raft TCP `7000`, HTTP `8000`, data dir `/data`.

#### `RAFT_START_MODE`

| Value | When |
|-------|------|
| `--bootstrap` | **First start of the first node only** (new cluster). |
| `--join http://<leader-raft-http>:8000` | **First start** of additional voters. |
| *(empty)* | Restart of a node that already has membership in `/data`. |

If `/data` already has Raft state, bootstrap/join flags are **ignored** and the node resumes membership from disk. Wipe volumes only when deliberately forming a brand-new cluster.

#### Notify URL rules

```bash
# Correct — this raft's own nginx on the same node/network
RAFT_NOTIFY_URL=http://nginx:80/api/ha/apply

# Wrong — localhost from raft container, VIP, or another node's backend
RAFT_NOTIFY_URL=http://127.0.0.1/api/ha/apply
RAFT_NOTIFY_URL=https://skylogs.example.com/api/ha/apply
```

Notify is best-effort: Raft commit succeeds even if the app is down; reconcile repairs later. Snapshot restore does **not** notify — boot runs `ha:reconcile`.

---

## 5. Production bootstrap procedure

### 5.1 Prerequisites

1. Three (or odd-N) hosts/VMs/K8s nodes with Docker (or equivalent) and a shared L2/L3 path for Raft TCP `7000`.
2. Built images: backend (API + Horizon), Raft (`apps/raft`), nginx, MongoDB, Redis.
3. Persistent volumes for: MongoDB data, Redis (optional), **Raft `/data`**, and app storage if your packaging requires it.
4. A strong shared `HA_NODE_SECRET` / `RAFT_NOTIFY_SECRET` (same on all nodes). Rotate by updating all nodes together.
5. Firewall: allow Raft `7000` and (as needed) Raft HTTP `8000` **between nodes**; allow HA peer HTTP between node backends; do **not** expose `/api/ha/*` to the public internet.

### 5.2 Bring-up order

1. **Create the peer network** (fixed subnet if you use static Raft IPs).
2. **Start node 1** with `RAFT_START_MODE=--bootstrap`, `HA_ENABLED=true`, full `HA_PEER_URLS`, local notify.
3. **Wait for leadership:**

   ```bash
   curl -s http://<node1-raft-http>:8000/status
   # expect: "is_leader": true
   ```

4. **Start node 2** with `RAFT_START_MODE=--join http://<node1-raft-ip>:8000`.
5. **Start node 3** the same way.
6. Confirm all three answer `/status` and agree on `leader`.
7. After the first successful membership, set `RAFT_START_MODE` empty (or leave compose as-is knowing data dir wins) for restarts.
8. Verify Horizon is up and processing `ha`; verify scheduler/cron is running on each Horizon container.
9. On followers, confirm config sync within ~30–60s (logs: `HA config sync applied a new snapshot`).

Local lab equivalent:

```powershell
# From repo root
.\ha\up.ps1
# Tear down (add -Volumes -RemoveNetwork for clean Raft reset)
.\ha\down.ps1
```

### 5.3 Boot sequence (application)

Each backend container entrypoint (`apps/backend/docker-entrypoint.sh`):

1. migrate / seed  
2. `php artisan ha:config-sync` (non-fatal)  
3. `php artisan ha:reconcile` (non-fatal)  
4. config/route cache, php-fpm  

A node that was down catches up config first, then alert state.

### 5.4 Manual commands

```bash
php artisan ha:config-sync   # follower pull (no-op on leader / when disabled)
php artisan ha:reconcile     # apply Raft keys / repair publishes
```

---

## 6. How data moves (ops-relevant)

### 6.1 Alert state (Raft)

1. Leader evaluates alerts / observers detect check or rule changes.
2. `AlertStateReplicator` builds keys `alert:{alertRuleId}:{type}:{instanceId}` with a monotonic version.
3. `PublishAlertStateJob` on queue `ha` → local Raft `POST /set` (leader only).
4. After commit, **every** Raft node POSTs to its own `RAFT_NOTIFY_URL` → `POST /api/ha/apply`.
5. `HaStateApplier` version-gates and writes local Mongo; **notifications are suppressed** on apply (no double-page).

Auth: header `X-Skylogs-HA-Secret: <HA_NODE_SECRET>`.

Tombstone: `"value": null` deletes the key.

### 6.2 Configuration (HTTP pull)

1. Every 30s, followers run `SyncHaConfigJob`.
2. Resolve leader backend URL via Raft `/status` + `HA_PEER_URLS`.
3. `GET /api/ha/config-sync?since=<lastApplied>` with the HA secret.
4. Leader returns `409` if not leader; otherwise a snapshot (or `{changed:false}` when up to date).
5. Follower upserts/deletes (leader wins), then records the applied version.

### 6.3 Reconciliation

- Every minute on every HA node: `ReconcileHaStateJob`.
- On promotion to leader: reconcile is dispatched.
- On boot: `ha:reconcile`.
- Follower: apply all Raft keys; tombstone local extras.
- Leader: inherit version counters, republish stale slots, sweep resolved slots older than `HA_STATE_RETENTION_DAYS`.

### 6.4 What is replicated where

**Config sync collections:**  
`users`, `roles`, `permissions`, `teams`, `endpoints`, `dataSources`, `alertRules` (minus Raft fields), `silentRules`, `statuses`, `services`, `skylogsInstances`, `profileAssets`, `profileEnvironments`, `profileServices`, `configSkylogs`, `configTelegrams`, `configSms`, `configCalls`, `configEmails`.

**Raft-owned alert rule fields (not in config sync):**  
`state`, `fireCount`, `notifyAt`, `acknowledgedBy`.

**Checks via Raft:** Prometheus, Grafana, Zabbix, API alert instances, Elastic, VictoriaLogs, Health.  
**Splunk:** no state replication (null projector/writer).

**Lag expectations:** alert state ≈ seconds (notify) + ≤1 minute (reconcile); configuration ≤ ~30 seconds.

---

## 7. HTTP contracts (HA)

All under `/api/ha/*`, middleware `haNodeAuth`. Outside `/api/v1` (not a client API).

| Method | Path | Who calls | Success | Notes |
|--------|------|-----------|---------|-------|
| `POST` | `/api/ha/apply` | Local Raft sidecar | `200` JSON | Apply one key/value or tombstone |
| `GET` | `/api/ha/config-sync` | Peer followers | `200` snapshot | Leader only; `409` if not leader |

Auth failures: `401` wrong/missing secret; `503` if `HA_ENABLED=false`; `403` if CIDR deny.

### Raft sidecar (for probes / ops)

| Method | Path | LB-friendly | Notes |
|--------|------|-------------|-------|
| `GET` | `/health` | **200** leader / **503** else | |
| `GET` | `/leader` | **200** leader / **503** else | |
| `GET` | `/status` | always 200 if HTTP up | `{node_id,is_leader,leader,state}` |
| `POST` | `/set` | leader only | App uses this; do not write from LB blindly |
| `GET` | `/get` | any node | Local FSM |
| `POST` | `/join` | leader only | Used at first join |

Full Raft contract: `apps/raft/README.Md`.

---

## 8. Load balancer guidance

**User / UI API traffic (optional VIP):**

- Can front nginx on all nodes for reads and most writes that are then config-synced.
- Prefer routing mutating UI traffic to the **current Raft leader** when possible (probe Raft `/health` or `/leader`), so configuration changes land on the leader immediately and followers pull within 30s.
- If you round-robin writes across nodes, followers still accept API writes locally but those config changes are **not** the HA source of truth until/unless they happen on the leader — treat leader-sticky writes as the production default.

**Must never use the VIP for:**

- `RAFT_NOTIFY_URL`
- `HA_PEER_URLS` values
- `RAFT_URL` (always local sidecar)

**Raft TCP `7000`:** peer-to-peer only; not through an L7 load balancer.

---

## 9. Security checklist

- [ ] Strong unique `HA_NODE_SECRET` / `RAFT_NOTIFY_SECRET` (identical cluster-wide).
- [ ] `/api/ha/*` not reachable from the public internet (private network / NetworkPolicy / firewall).
- [ ] Optionally set `HA_ALLOWED_CIDRS` to peer + sidecar ranges (test carefully — Docker/K8s source IPs can surprise you).
- [ ] TLS between nodes if traffic leaves a trusted network (terminate at nginx or mesh).
- [ ] Do not log the HA secret.
- [ ] Rotate secret by rolling all nodes with the new value (brief apply/config-sync failures until all match).

---

## 10. Monitoring and health

### Probes

| Probe | Target | Healthy |
|-------|--------|---------|
| Raft role (LB) | `GET http://<raft>:8000/health` or `/leader` | 200 = leader, 503 = follower |
| Raft liveness | `GET /status` | HTTP 200 + JSON body |
| Backend | php-fpm / nginx health as you already use | |
| Mongo | `mongosh` ping | |
| Horizon | Horizon dashboard / supervisor metrics; queue `ha` depth | |

There is **no** dedicated Laravel `/api/ha/health` route.

### Logs to watch

| Message / pattern | Meaning |
|-------------------|---------|
| `HA role transition` | Node became leader or follower |
| `HA config sync applied a new snapshot` | Follower applied config |
| `HA config sync skipped, the leader is unreachable` | Peer map / network / leader down |
| `HA reconciliation finished` | Reconcile completed |
| Raft unreachable warnings | Treat as follower; evaluation stops until Raft recovers |
| `PublishAlertStateJob` failures | Will retry; reconcile republishes |

### Metrics worth exporting

- Raft `is_leader` per node (from `/status`)
- Horizon `ha` queue wait time / failed jobs
- Config sync lag (time since last successful apply on followers)
- Alert evaluation only on leader (followers should not run CheckPrometheusJob etc.)

---

## 11. Failure modes and ops playbooks

### Leader crash (preferred test: SIGKILL)

```bash
# Crash — membership preserved
docker kill <raft-or-node-leader>

# Wait ~election time, then:
curl -s http://<survivor-raft>/status
# New leader should show is_leader=true
```

Expected:

- Survivors elect a new leader.
- New leader starts evaluation jobs and may reconcile.
- Followers continue config pull against the new leader once `HA_PEER_URLS` resolves.
- Bring old node back with **empty** start mode; it resumes from `/data`.

### Graceful stop (planned leave)

SIGTERM on Raft **removes the node from membership**. Prefer this only for planned decommission. For crash drills, always kill.

### Raft unreachable on a node

App fail-closes: not leader → no evaluation / no publishes. Fix sidecar, network, or `RAFT_URL`.

### Follower far behind / empty Mongo

1. Ensure HA enabled, secret, peers, Horizon, scheduler.  
2. `php artisan ha:config-sync`  
3. `php artisan ha:reconcile`  
4. Check raft `GET /get` has keys and `/api/ha/apply` accepts notify (auth).

### Split brain / two leaders

Should not happen with a majority Raft cluster. If you see disagreement:

1. Confirm odd voters and network partitions.  
2. Check each `/status` `leader` field.  
3. Do **not** bootstrap a second cluster against the same logical deployment.  
4. Restore quorum; never wipe `/data` on a majority of nodes casually.

### Replacing a dead node

1. Provision host with same `RAFT_NODE_ID` / `RAFT_ADV_ADDR` **or** a new id.  
2. If new id: join via `--join http://<current-leader-http>:8000` and update `HA_PEER_URLS` everywhere.  
3. If same id and empty disk: join again; if disk restored from backup, start with empty mode.  
4. Wait for config-sync + reconcile before sending user traffic there.

### Full cluster reset (destructive)

Wipe **all** Raft volumes, then bootstrap node1 and re-join others. Application Mongo on followers will re-sync from the new leader’s config + Raft state; treat as disaster recovery.

---

## 12. Capacity and timing defaults

| Parameter | Default | Ops note |
|-----------|---------|----------|
| Config sync interval | 30s | Acceptable config lag |
| Reconcile interval | 1m | Safety net for missed notify |
| Leader status cache | 2s | Failover detection ~ few seconds |
| State retention | 7 days | Resolved slot tombstones on leader |
| Publish job retries | 5 (job) + Raft client retries | Backoff; then reconcile |

Size Mongo/Redis/Horizon like a single-node Skylogs install **per node**. Raft store grows with active alert slots; retention sweep limits resolved history.

---

## 13. Upgrade / rolling restart

1. Prefer rolling one node at a time; keep a Raft majority up.  
2. Drain or kill one follower first (not the only way, but safer).  
3. After upgrade, confirm `/status`, config-sync logs, and `ha` queue.  
4. Unknown config collections on older followers are ignored (rolling upgrade friendly).  
5. Never flip `HA_ENABLED` mid-cluster inconsistently — all nodes should agree.

---

## 14. Production checklist

**Infrastructure**

- [ ] Odd number of full nodes (3+)
- [ ] Persistent Raft `/data` per node
- [ ] Independent MongoDB per node
- [ ] Independent Redis per node
- [ ] Horizon running with `supervisor-ha` / queue `ha`
- [ ] Scheduler/cron active on each node (Horizon image)
- [ ] Raft TCP 7000 open between peers
- [ ] Stable `RAFT_ADV_ADDR` values

**Configuration**

- [ ] `HA_ENABLED=true` on all nodes
- [ ] Identical `HA_NODE_SECRET` / `RAFT_NOTIFY_SECRET`
- [ ] `RAFT_NOTIFY_HEADER=X-Skylogs-HA-Secret`
- [ ] `RAFT_NOTIFY_URL` points at **local** nginx `/api/ha/apply`
- [ ] Complete identical `HA_PEER_URLS` on all nodes
- [ ] `RAFT_URL` points at local sidecar
- [ ] Bootstrap only once on first node; join others once; empty mode on restart

**Verification**

- [ ] Exactly one `is_leader: true` under normal conditions
- [ ] Write a test config on leader → appears on follower within ~30s
- [ ] Fire a test alert on leader → state appears on follower without duplicate notifications
- [ ] Kill leader → new leader elected → evaluation resumes
- [ ] Restart old leader → rejoins without `--join` when `/data` intact
- [ ] `/api/ha/*` returns 401 without secret; not publicly exposed

**Do not confuse with**

- [ ] `CLUSTER_TYPE` / agent sync (`/api/cluster/*`) — separate feature

---

## 15. Local lab quick reference

```powershell
# Start 3-node stack
.\ha\up.ps1

# Status
curl.exe -s http://127.0.0.1:8801/status
curl.exe -s http://127.0.0.1:8802/status
curl.exe -s http://127.0.0.1:8803/status

# Failover drill (kill, do not stop)
docker kill skylogs_raft-1

# Tear down + wipe Raft membership
.\ha\down.ps1 -Volumes -RemoveNetwork
```

Compose overrides: `ha/docker-compose.node1.yml`, `node2.yml`, `node3.yml`.  
Shared env: `ha/node.env`.

---

## 16. Key code & docs index

| Path | Role |
|------|------|
| `apps/backend/config/ha.php` | HA + Raft client config |
| `apps/backend/.env.example` | Documented env vars |
| `apps/raft/README.Md` | Raft HTTP contract & smoke tests |
| `ha/up.ps1` / `ha/down.ps1` | Local 3-node lifecycle |
| `ha/docker-compose.node*.yml` | Per-node overrides |
| `apps/backend/docker-entrypoint.sh` | Boot config-sync + reconcile |
| `apps/backend/routes/api.php` | `/api/ha/apply`, `/api/ha/config-sync` |
| `apps/backend/routes/console.php` | Leader-gated schedule + HA jobs |
| `apps/backend/config/horizon.php` | `supervisor-ha` → queue `ha` |
| `App\Services\Ha\*` | Leader, Raft client, sync, reconcile, apply |
| `App\Http\Middleware\HaNodeAuth` | Shared secret (+ optional CIDR) |

---

## 17. Glossary

| Term | Meaning |
|------|---------|
| Leader | Raft-elected node; alone evaluates alerts and accepts config-sync reads |
| Follower | Replicates state via Raft notify + pulls config; does not evaluate |
| Notify | Raft → local `POST /api/ha/apply` after a log entry applies |
| Config sync | Follower HTTP pull of configuration snapshot from leader |
| Reconcile | Periodic repair of local Mongo vs Raft KV |
| Tombstone | Raft delete (`value: null`) |
| Peer URL map | `HA_PEER_URLS` — Raft address → backend base URL |
