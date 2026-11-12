# SkyLogs Sentinel

Sentinel monitors reachability between SkyLogs **zone clusters** (`CLUSTER_TYPE=main` and `CLUSTER_TYPE=agent`). Each zone runs its own Sentinel. Sentinels exchange signed heartbeats; if the peer becomes unreachable, that zone’s Sentinel fires an **API alert** to **its own** SkyLogs backend, which notifies configured endpoints (SMS, email, Telegram, etc.).

```
┌─────────────────────────────┐         ┌─────────────────────────────┐
│  Zone A (e.g. main)         │         │  Zone B (e.g. agent)        │
│                             │         │                             │
│  SkyLogs Backend            │         │  SkyLogs Backend            │
│       ▲ fire-alert          │         │       ▲ fire-alert          │
│       │                     │         │       │                     │
│  Sentinel A ◄──heartbeat──► │─────────│ Sentinel B                  │
│  :9191                      │         │  :9191                      │
└─────────────────────────────┘         └─────────────────────────────┘
```

---

## Prerequisites

- Docker & Docker Compose
- A running SkyLogs stack in **each** zone (backend reachable for `POST /api/v1/fire-alert`)
- Network path between zones so each Sentinel can reach the peer’s `/heartbeat` URL
- An **API alert rule** created in **each** zone (token goes into that zone’s `config.yaml`)

---

## 1. Create an API alert in each zone

Do this **per zone** (main and agent), in that zone’s SkyLogs UI/dashboard:

1. Create an **API** alert rule (e.g. `sentinel-peer-down`).
2. Attach the endpoints that should be notified (SMS, email, Telegram, …).
3. Copy the alert rule **API token**.

Details: [ApiAlert.md](ApiAlert.md).

Fire endpoint used by Sentinel:

```http
POST /api/v1/fire-alert
Authorization: Bearer <API_TOKEN>
Content-Type: application/json

{
  "instance": "<from config alert.instance>",
  "description": "No heartbeat received for more than 15s"
}
```

Put that zone’s token in **that** zone’s Sentinel `config.yaml` under `alert.token`.  
Point `alert.webhook_url` at **that** zone’s backend (not the peer’s).

---

## 2. Configure `config.yaml`

Copy the example and edit:

```bash
cp apps/sentinel/config.example.yaml apps/sentinel/config.yaml
```

Example (`apps/sentinel/config.example.yaml`):

```yaml
server:
  listen: ":9191"

sentinel:
  id: arvan-bamdad-sentinel   # Unique ID for this instance
  role: secondary             # primary / secondary (informational)

heartbeat:
  target_url: "http://PEER_SENTINEL_HOST:9191/heartbeat"
  interval: 5s
  timeout: 3s
  fail_after: 15s

alert:
  webhook_url: "http://nginx:80/api/v1/fire-alert"  # THIS zone's backend
  token: "the-alert-rule-token"                     # API token from THIS zone
  instance: "arvan-bamdad-sentinel"
  retry_interval: 10s

security:
  shared_secret: "CHANGE_ME_SAME_ON_BOTH_SIDES"
  allowed_drift: 10s
```

| Key | Purpose |
|-----|---------|
| `sentinel.id` | Unique name for this Sentinel |
| `heartbeat.target_url` | Peer Sentinel `GET /heartbeat` URL |
| `heartbeat.fail_after` | How long without a successful heartbeat before alerting |
| `alert.webhook_url` | This zone’s SkyLogs `fire-alert` URL |
| `alert.token` | API alert token created in **this** zone |
| `alert.instance` | Instance id sent in the fire payload |
| `security.shared_secret` | **Must match** on both Sentinels (HMAC signing) |
| `security.allowed_drift` | Max clock skew for signed requests |

**Important**

- Sentinels load **`config.yaml` only** (not the `SENTINEL_*` env vars in root `docker-compose.yml`).
- Both sides must share the same `shared_secret`.
- Each side alerts to **its own** backend with **its own** API token.

### Suggested pairing

| Zone | `heartbeat.target_url` | `alert.webhook_url` | `alert.token` |
|------|------------------------|---------------------|---------------|
| Main | Agent Sentinel `/heartbeat` | Main backend `/api/v1/fire-alert` | Token from Main API alert |
| Agent | Main Sentinel `/heartbeat` | Agent backend `/api/v1/fire-alert` | Token from Agent API alert |

---

## 3. Run with Docker Compose (repo root)

Sentinel is defined in root `docker-compose.yml` under profile `full`:

```yaml
sentinel:
  profiles: ["full"]
  build: apps/sentinel
  restart: always
  volumes:
    - ./apps/sentinel/config.yaml:/app/config.yaml:ro
  ports:
    - "9191:9191"
  networks:
    - skylogs_net
```

Start (with the rest of the stack as needed):

```bash
# Ensure config.yaml exists and is filled in
cp apps/sentinel/config.example.yaml apps/sentinel/config.yaml
# edit config.yaml

docker compose --profile full up -d --build sentinel
```

Or start the full profile:

```bash
docker compose --profile full up -d --build
```

Inside the compose network, a typical alert URL is:

`http://nginx:80/api/v1/fire-alert`

For a peer on another host/zone, set `heartbeat.target_url` to a reachable address, e.g.:

`https://agent-zone.example.com:9191/heartbeat`

---

## 4. Run standalone (build image)

```bash
cd apps/sentinel
docker build -t skylogs-sentinel .
docker run -d --name sentinel \
  -p 9191:9191 \
  -v "$(pwd)/config.yaml:/app/config.yaml:ro" \
  skylogs-sentinel
```

The binary expects `config.yaml` in the working directory (`/app`).

---

## 5. HTTP endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/heartbeat` | Peer heartbeat (HMAC: `X-SkyLogs-Timestamp`, `X-SkyLogs-Signature`) |
| `GET` | `/status` | Local health snapshot (`sentinel_id`, status, last seen, uptime) |

Example:

```bash
curl http://localhost:9191/status
```

---

## 6. How alerting works

1. Periodically `GET` peer `heartbeat.target_url` with HMAC headers.
2. Peer validates signature/`allowed_drift` and marks itself seen.
3. On successful send, local state is marked seen.
4. If sends fail and time since last seen exceeds `fail_after`, Sentinel posts to `alert.webhook_url` with Bearer `alert.token`.
5. That zone’s SkyLogs fires the API alert and delivers to attached endpoints.

Alert fires once per unhealthy transition.

---

## 7. Checklist

- [ ] API alert rule created on **main** zone; token in main Sentinel `config.yaml`
- [ ] API alert rule created on **agent** zone; token in agent Sentinel `config.yaml`
- [ ] Endpoints attached to both alert rules
- [ ] `shared_secret` identical on both Sentinels
- [ ] Each `target_url` points at the **other** zone’s Sentinel `/heartbeat`
- [ ] Each `webhook_url` points at **local** zone backend `/api/v1/fire-alert`
- [ ] Port `9191` (or chosen listen port) reachable between zones
- [ ] `curl` `/status` on both sides looks healthy after start

---

## Related

- API alert contract: [ApiAlert.md](ApiAlert.md)
- Compose service: root `docker-compose.yml` → `sentinel`
- Example config: `apps/sentinel/config.example.yaml`
- Source: `apps/sentinel/`
