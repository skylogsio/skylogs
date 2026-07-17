<p align="center">
  <img src="public/images/skylogLogo1000x300.png" alt="Skylogs" width="500"/>
</p>

<h3 align="center">Open-source incident response that survives the incident.</h3>

<p align="center">
  Alerting, on-call, and incident management built on shared responsibility —<br/>
  with a multi-zone, self-healing architecture designed to stay up when your datacenter doesn't.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License">
  <img src="https://img.shields.io/github/v/release/skylogsio/skylogs" alt="Release">
  <img src="https://img.shields.io/badge/docs-docs.skylogs.io-brightgreen.svg" alt="Documentation">
  <img src="https://img.shields.io/badge/Docker-supported-blue.svg" alt="Docker">
</p>

---

<!-- TODO: Add a screenshot or short GIF of the main dashboard here.
     This is the single highest-impact thing you can add to this README. -->

## What is Skylogs?

Skylogs is an **open-source incident response platform** — an alternative to tools like incident.io, PagerDuty, and Opsgenie that you can run on your own infrastructure.

It consolidates alerts from your observability stack (Prometheus, Grafana, Zabbix, Datadog, Splunk, ELK, and anything with a webhook), routes them to the right people through escalation policies and on-call schedules, and manages the full incident lifecycle — from first alert to root cause analysis and postmortem.

Skylogs is built on one core belief:

> **Incident response is an organizational responsibility, not just an infrastructure concern.**

That belief shapes the product. Instead of concentrating incident response in a single ops team, Skylogs implements a **shared responsibility and risk distribution model** rooted in DevOps culture and security standards: every team owns its alerts, its escalation paths, and its part of the response — with RBAC and clear ownership boundaries keeping it safe.

## Why Skylogs?

**🔓 Truly open source.** MIT-licensed, self-hosted, no feature-gated core. Your alert and incident data never leaves your infrastructure.

**🤝 Shared responsibility by design.** Risk and response are distributed across teams, not funneled through a single NOC. Team-scoped alert ownership, per-user notification preferences, and role-based access control make cross-team incident response practical instead of chaotic.

**🔍 Root cause, not just paging.** A built-in **troubleshooting workspace** helps responders trace an incident to its root cause during the incident — and export the result as an **RCA report** or **postmortem** when it's over.

**🌍 Built to survive disasters — including its own.** An incident platform is the last thing that's allowed to go down. Skylogs is architected for exactly that scenario. See the architecture section below — this is where Skylogs differs most from other self-hosted alternatives.

## Resilience architecture

Most self-hosted alerting tools have a single point of failure: themselves. Skylogs ships with two independent clustering layers to remove it.

### Multi-zone mode — surviving datacenter disasters

Skylogs can be deployed across **multiple zones** (datacenters, regions, or sites). Each zone runs a full Skylogs deployment and is monitored by **Sentinel**, a lightweight Go service that heartbeats between zones.

- **Alert data stays local to each zone** — the zone closest to the failing system keeps paging, even when it's cut off from the rest of the world.
- **Organizational data is synchronized across zones** — users, teams, endpoints, clusters, schedules, and escalation policies are the same everywhere, so any zone can run a complete response on its own.
- If a zone goes dark, the surviving zones detect it via Sentinel heartbeats. You are never blind during a datacenter disaster — which is precisely when you need your incident platform the most.

### High-availability mode — surviving server outages

Within a zone, Skylogs runs in an HA cluster built on the **Raft consensus algorithm**. If a node fails, the cluster elects a new leader and continues operating — no manual failover, no lost escalations, no dropped pages during ordinary server outages or internal zone incidents.

Together, these two layers form a simple mental model: **HA inside the zone, federation across zones.** Strong consistency where a lost page is unacceptable; independent operation where a network partition must never blind you.

<!-- TODO: Add an architecture diagram here showing zones, Sentinel heartbeats, and the Raft cluster within a zone. -->

## Key features

### Alert management
- Ingest alerts from any observability tool via pre-built integrations, REST API, and webhooks
- Correlation and deduplication to cut alert noise
- Routing by severity, source, and tags; suppression and maintenance windows

### On-call & escalation
- On-call rotations, schedules, shift swaps, and handoffs
- Automated escalation chains with configurable timeouts
- Multi-channel notifications: phone call, SMS, email, Slack, Microsoft Teams, Telegram
- **Endpoint verification** so a critical page is never sent to a dead channel

### Incident response
- Turn alerts into structured incidents with clear ownership
- Troubleshooting workspace for live root cause analysis
- Exportable **RCA reports** and **postmortems**
- MTTA / MTTR / SLA tracking and long-period SLA reports per service

### Visibility
- Public or private branded status pages with subscriber notifications
- Alert and incident analytics, trend reporting, custom dashboards
- Full audit logs

## Quick start

**Prerequisites:** Docker and Docker Compose.

```bash
git clone https://github.com/skylogsio/skylogs.git
cd skylogs
docker compose up -d --build
```

Then open `http://localhost:PORT` and log in with the default credentials.
<!-- TODO: fill in the real port, default credentials, and the 2–3 steps to fire a first test alert.
     Target: a stranger goes from clone to receiving a test notification in under 10 minutes. -->

Full instructions, production deployment (including multi-zone and HA setup), and integration guides are in the documentation — see the links at the end of this page.

## Who is it for?

- **SRE & DevOps teams** centralizing alerts from multiple monitoring platforms and running on-call
- **Network & datacenter teams** — first-class Zabbix integration and verified endpoint delivery for critical infrastructure alerts
- **Security teams** coordinating SOC response with Splunk, ELK, and SIEM integrations
- **Development teams** who want ownership of their own services' alerts without adopting a heavyweight ops process
- **Engineering managers** tracking on-call load, response metrics, and escalation coverage

## Roadmap

Our roadmap is organized around one long-term vision: **moving incident response from reactive to rehearsed.**

### 🧪 Incident simulation — the flagship

Today, testing your incident readiness means organizing a manual game day a couple of times a year. Skylogs is building something different: an **automated incident simulation engine**.

Describe your infrastructure topology (hosts, VMs, Kubernetes workloads, databases, services), pick a fault — say, a hypervisor outage taking down 100 VMs, two Kubernetes workers, and a MySQL server — and Skylogs regenerates the **full realistic alert cascade** in native datasource formats (Prometheus/Alertmanager first; Zabbix, Splunk, PMM and others to follow), fires it through the *real* ingestion pipeline, and simulates the complete incident chain: node-down alerts, pod CrashLoopBackOff storms, blackbox service-down detection, escalation, and notification.

Every run produces a **readiness report**: who would have been paged and when, which alerts went unrouted, which notification endpoints are dead, where escalation chains have gaps, whether root cause analysis identified the injected fault, and the simulated SLA and financial impact.

Planned in stages:
- **Sandbox mode** — the full pipeline runs, notifications are captured but not delivered
- **Fire-drill mode** — scheduled drills with real notification delivery, proving your endpoints end to end
- **Scenario library** — declarative, version-controlled YAML scenarios that can be shared and community-contributed
- **Topology import** — building the dependency graph automatically from Kubernetes, Zabbix, and Prometheus metadata

For teams under regulatory pressure (DORA, NIS2, SOC 2, ISO 27001), each simulation run doubles as timestamped, repeatable evidence of incident response testing.

### 🧩 Unified cluster management

Sentinel (cross-zone federation) and the Raft HA layer will progressively converge into a single cluster-management experience: one configuration, one deployment, and one status view covering both intra-zone health and cross-zone federation — while keeping the two consensus domains strictly separate by design.

### 🔐 Platform & enterprise

- SSO integration
- SOC 2 Type II compliance
- Expanded integration catalog

See the full roadmap link at the end of this page for details and progress.

## Contributing

We welcome contributions — code, integrations, documentation, and (soon) simulation scenarios. Read the contributing guidelines linked below to get started, and join the conversation in GitHub Discussions.

## License

MIT — see the license link below.

## Links

**Documentation**
- 📖 Documentation home: https://docs.skylogs.io
- 🚀 Installation guide: https://docs.skylogs.io/installation
- 🌍 Multi-zone & HA deployment: https://docs.skylogs.io/deployment
- 🔌 Integrations: https://docs.skylogs.io/integrations
- 🧾 API reference: https://docs.skylogs.io/api
- 📘 User guide: https://docs.skylogs.io/user-guide
- 🛠️ Admin guide: https://docs.skylogs.io/admin-guide

**Project**
- 🌐 Website: https://skylogs.io
- 🗺️ Roadmap: https://github.com/skylogsio/skylogs/projects
- 🤝 Contributing guidelines: https://docs.skylogs.io/contributing
- 📄 License: https://github.com/skylogsio/skylogs/blob/main/LICENSE
- 📦 Releases: https://github.com/skylogsio/skylogs/releases

**Community & support**
- 💬 GitHub Discussions: https://github.com/skylogsio/skylogs/discussions
- 🐛 Issue tracker: https://github.com/skylogsio/skylogs/issues
- ✉️ Email: support@skylogs.io

---

<p align="center">Made with ❤️ by the Skylogs team · <a href="https://skylogs.io">skylogs.io</a></p>
