# Skylogs Raft (KV sidecar)

Hashicorp Raft key-value cluster with an HTTP API. Writes go through the leader; reads are local to the node you call.

**Agents: read this file only.** It is the contract for ports, endpoints, request/response shapes, leadership rules, and how to run / HA-test the cluster. You do not need to open Go sources for normal integration or HA testing.

---

## Quick facts

| Item | Value |
|------|--------|
| HTTP API port (in-container / default) | `8000` |
| Raft TCP port | `7000` |
| Write path | `POST /set` — **leader only** |
| Read path | `GET /get` — any node (local FSM; may lag briefly after a write) |
| List all keys | `GET /get` with **no** `key` query param |
| Leadership probe (for LB) | `GET /leader` and `GET /health` — `200` if this node is leader, `503` otherwise |
| Join cluster | `POST /join` on the **leader** (usually done by the joining node itself via `--join`) |

Base URL examples:

```text
http://127.0.0.1:8001   # docker-compose HA: node1 published port
http://127.0.0.1:8002   # node2
http://127.0.0.1:8003   # node3
http://<host>:8000      # single-node / bare metal default
```

---

## Leadership rules (must follow)

1. **Discover the leader** before writes: call `GET /status` or `GET /leader` on any node.
2. **`POST /set` and `POST /join` must hit the leader.** Followers respond with plain text `not the leader` and HTTP `500`.
3. There is **no automatic HTTP redirect** to the leader. Clients must retry on the leader address from `/status` or `/leader`.
4. `GET /get` works on any node; it reads that node’s local FSM (eventually consistent with the leader log).
5. On process shutdown (**SIGTERM**), the node attempts to **remove itself** from the cluster (`RemoveServer`). That is a planned leave, not a crash. For HA crash/failover tests use `docker kill` (SIGKILL) so membership is preserved; then `docker start` resumes from disk.

`leader` / advertise addresses in responses are **Raft** addresses (`<advertise-address>:<raft-port>`), e.g. `172.28.7.11:7000`, not the HTTP URL.

---

## HTTP API

All JSON success bodies use `Content-Type: application/json` unless noted. Many errors use plain text via `http.Error`.

### `GET /health`

Liveness / role probe. **HTTP 200** if this node is the Raft leader, **503** otherwise.

```json
{
  "role": "leader",
  "state": "Leader",
  "node_id": "node1",
  "leader": "172.28.7.11:7000"
}
```

`role` is `"leader"` or `"follower"`. `state` is the Raft state string (`Leader`, `Follower`, `Candidate`, …).

```bash
curl -i http://127.0.0.1:8001/health
```

---

### `GET /leader`

Same status semantics as `/health` for load balancers: **200** = this node is leader, **503** = not leader.

```json
{
  "leader": true,
  "address": "172.28.7.11:7000"
}
```

On a follower (`503`):

```json
{
  "leader": false,
  "address": "172.28.7.11:7000"
}
```

`address` is the current Raft leader address (may be empty during election).

```bash
curl -i http://127.0.0.1:8001/leader
```

---

### `GET /status`

Cluster view for the contacted node. Always **200** if the HTTP server is up (does not use 503 for followers).

```json
{
  "node_id": "node1",
  "is_leader": true,
  "leader": "172.28.7.11:7000",
  "state": "Leader"
}
```

```bash
curl -s http://127.0.0.1:8001/status
```

---

### `POST /set`

Replicate a key/value through the leader FSM.

**Request body:**

```json
{
  "key": "username",
  "value": "mobin"
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `key` | yes | string |
| `value` | yes for set | any JSON value (string, number, object, array, bool). Stored as the **raw JSON text** of that field (e.g. string `"mobin"` is stored including the JSON quotes as characters). **`null` (or omitted) deletes the key** (tombstone). |

**Success (`200`):**

```json
{
  "status": "ok"
}
```

**Not leader (`500`, plain text body):**

```text
not the leader
```

**Bad JSON / read error:** `400` with plain text error.

```bash
curl -i -X POST http://127.0.0.1:8001/set \
  -H "Content-Type: application/json" \
  -d '{"key":"username","value":"mobin"}'

# Delete
curl -i -X POST http://127.0.0.1:8001/set \
  -H "Content-Type: application/json" \
  -d '{"key":"username","value":null}'
```

---

### `GET /get`

#### Single key

```
GET /get?key=<key>
```

**Found (`200`):**

```json
{
  "key": "username",
  "value": "\"mobin\""
}
```

`value` is the stored string (raw JSON text from `/set`). For a JSON string input, expect escaped quotes in the response string as shown above. For an object value sent as `"value":{"a":1}`, expect `"value":"{\"a\":1}"` in this map response.

**Missing key (`404`, plain text):**

```text
key not found
```

```bash
curl -i "http://127.0.0.1:8001/get?key=username"
```

#### All keys

Omit `key` (or pass an empty `key`):

```
GET /get
```

**Success (`200`):** JSON object map of all keys → stored values.

```json
{
  "username": "\"mobin\"",
  "language": "\"Go\""
}
```

Empty store:

```json
{}
```

```bash
curl -s http://127.0.0.1:8001/get
```

---

### `POST /join`

Add a voter. **Leader only.** Normally invoked by a new node’s process using `--join http://<leader-http>/`; you can also call it manually.

**Request:**

```json
{
  "node_id": "node2",
  "raft_address": "172.28.7.12:7000"
}
```

**Success (`200`):** `{"status":"ok"}`  
**Not leader / Raft error:** `500` plain text (e.g. `not the leader`).

```bash
curl -i -X POST http://127.0.0.1:8001/join \
  -H "Content-Type: application/json" \
  -d '{"node_id":"node2","raft_address":"172.28.7.12:7000"}'
```

---

## Process flags / container env

Binary flags (entrypoint builds these from env):

| Flag | Env (Docker) | Required | Default | Meaning |
|------|----------------|----------|---------|---------|
| `--node-id` | `RAFT_NODE_ID` | yes | — | Unique Raft ID |
| `--advertise-address` | `RAFT_ADV_ADDR` | yes | — | Address **other nodes** use to reach this node (no port) |
| `--bind-address` | fixed `0.0.0.0` in entrypoint | no | `0.0.0.0` | Listen address |
| `--raft-port` | fixed `7000` | no | `7000` | Raft TCP |
| `--http-port` | fixed `8000` | no | `8000` | HTTP API |
| `--data-dir` | fixed `/data` | yes | — | Persistent Raft + FSM data |
| `--bootstrap` | via `RAFT_START_MODE` | first node only | false | Create a new single-node cluster |
| `--join` | via `RAFT_START_MODE` | joining nodes | — | Leader HTTP base, e.g. `http://172.28.7.11:8000` |
| `--notify-url` | `RAFT_NOTIFY_URL` | no | — | If set, POST each committed change to this URL (local app apply hook) |
| `--notify-secret` | `RAFT_NOTIFY_SECRET` | no | — | Shared secret for notify requests |
| `--notify-header` | `RAFT_NOTIFY_HEADER` | no | `X-Raft-Notify-Secret` | Header name that carries `--notify-secret` |

`RAFT_START_MODE` examples:

- `--bootstrap` — first node of a new cluster
- `--join http://172.28.7.11:8000` — join existing cluster (POST `/join` after ~2s)
- empty — restart an already-membered node (do **not** pass bootstrap/join again)

If `--data-dir` already has files, the process **ignores** bootstrap and join flags and resumes membership from disk (no second `/join`). Wipe volumes (`docker compose down -v`) to form a brand-new cluster.

---

## Local apply notify (optional)

When `--notify-url` is set, **every node** (leader and followers) POSTs to that URL after a log entry is applied to the local FSM. Snapshot restore does **not** notify; the app should reconcile with `GET /get` after restart.

Request:

```http
POST <notify-url>
Content-Type: application/json
<notify-header>: <notify-secret>   # only if --notify-secret is set
```

```json
{ "key": "username", "value": "\"mobin\"" }
```

Delete / tombstone:

```json
{ "key": "username", "value": null }
```

`value` is the stored raw JSON text for sets (same as `/get`), or JSON `null` for deletes. Notify is asynchronous and best-effort: Raft commit succeeds even if the app is down; the app must reconcile later.

Example (same Docker Compose network — this raft’s own nginx/backend service name):

```bash
RAFT_NOTIFY_URL=http://nginx_back:80/api/ha/apply
RAFT_NOTIFY_SECRET=shared-secret
RAFT_NOTIFY_HEADER=X-Skylogs-HA-Secret
```

Do **not** use `localhost`, a public VIP, or another node’s backend. In a multi-node compose, `raft-2` must notify `http://nginx_back-2:80/api/ha/apply` (its pair), not node 1.
---

## Local HA test environment

Three-node cluster with fixed IPs, defined in this directory:

```bash
cd apps/raft
docker compose up -d --build
```

| Node | Container | Raft IP | Host HTTP |
|------|-----------|---------|-----------|
| node1 | `raft-node1` | `172.28.7.11` | `http://127.0.0.1:8001` |
| node2 | `raft-node2` | `172.28.7.12` | `http://127.0.0.1:8002` |
| node3 | `raft-node3` | `172.28.7.13` | `http://127.0.0.1:8003` |

Startup order: node1 bootstraps; node2/node3 join `http://172.28.7.11:8000`. Data dirs: Docker volumes `raft_node1_data`, `raft_node2_data`, `raft_node3_data`.

### Smoke checklist

```bash
# 1) Wait until one node reports leader
curl -s http://127.0.0.1:8001/status
curl -s http://127.0.0.1:8002/status
curl -s http://127.0.0.1:8003/status

# 2) Write only to the leader (example: node1 is leader)
curl -s -X POST http://127.0.0.1:8001/set \
  -H "Content-Type: application/json" \
  -d '{"key":"ha","value":"ok"}'

# 3) Read from followers + list all
curl -s "http://127.0.0.1:8002/get?key=ha"
curl -s http://127.0.0.1:8003/get

# 4) Failover: crash the leader (use kill, not stop — SIGTERM removes the node from the cluster)
docker kill raft-node1
sleep 3
curl -s http://127.0.0.1:8002/status
curl -s http://127.0.0.1:8003/status
# POST /set to whichever reports is_leader=true

# 5) Bring old leader back (existing /data resumes membership; no --join needed)
docker start raft-node1
```

Reset cluster state:

```bash
docker compose down -v
```

Helpers:

```bash
./scripts/ha-smoke.sh          # Git Bash / WSL / Linux
./scripts/ha-smoke.ps1         # PowerShell on Windows
```

---

## Bare-metal / one-off Docker

### Node 1 (bootstrap)

```bash
./skylogs-raft \
  --node-id node1 \
  --bind-address 0.0.0.0 \
  --advertise-address 192.168.56.11 \
  --raft-port 7000 \
  --http-port 8000 \
  --data-dir ./data/node1 \
  --bootstrap
```

### Node 2 / 3 (join leader HTTP)

```bash
./skylogs-raft \
  --node-id node2 \
  --bind-address 0.0.0.0 \
  --advertise-address 192.168.56.12 \
  --raft-port 7000 \
  --http-port 8000 \
  --data-dir ./data/node2 \
  --join http://192.168.56.11:8000
```

Data directory ownership for the image user (`raft`, uid `10001`):

```bash
sudo chown -R 10001:10001 ./data
```

---

## Typical agent workflow

1. `docker compose up -d --build` in `apps/raft` (or start three processes as above).
2. Poll `GET /status` until one node has `"is_leader": true`.
3. `POST /set` **only** to that node’s HTTP URL.
4. `GET /get?key=...` or `GET /get` on any node to verify replication.
5. For HA: stop the leader container, poll `/status` on survivors, write again on the new leader.
6. Do not invent endpoints (`/save`, `/state`, etc.) — this service only exposes `/health`, `/leader`, `/status`, `/set`, `/get`, `/join`.

---

## Endpoint index

| Method | Path | Leader required? | Success | Notes |
|--------|------|------------------|---------|--------|
| GET | `/health` | — | 200 leader / 503 else | LB probe |
| GET | `/leader` | — | 200 leader / 503 else | `{leader,address}` |
| GET | `/status` | — | 200 | `{node_id,is_leader,leader,state}` |
| POST | `/set` | **yes** | 200 `{"status":"ok"}` | body `{key,value}` |
| GET | `/get?key=` | no | 200 `{key,value}` | 404 plain `key not found` |
| GET | `/get` | no | 200 object map | all keys when `key` omitted |
| POST | `/join` | **yes** | 200 `{"status":"ok"}` | `{node_id,raft_address}` |
