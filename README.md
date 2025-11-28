# Skylogs 

<div align="center">
  <img src="https://github.com/skylogsio/skylogs/raw/main/public/images/skylogLogo1000x300.png" >
  
  <h3>A unified platform for alert and incident management that integrates seamlessly with your observability solutions</h3>
  
  [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
  [![Laravel](https://img.shields.io/badge/Laravel-10.x-red.svg)](https://laravel.com)
  [![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
  [![Docker](https://img.shields.io/badge/Docker-supported-blue.svg)](https://docker.com)
</div>

---

## 🚀 About Skylogs

**Skylogs** is an open-source, end-to-end **alert and incident management platform** built to bridge the gap between observability tools and incident response.  

It is designed with **shared responsibility** and **personalization** in mind, making it a flexible platform for use across all teams in an organization.  

Skylogs consolidates alerts from multiple observability systems, applies intelligent routing and escalation, and ensures the right people are notified through their preferred communication channels.  

👉 Our belief: **Incident response is not only an infrastructure concern — it’s an organizational responsibility.** Skylogs helps implement this mission by giving every team the tools they need to act quickly and effectively.  

---

## ✨ Key Features

### 🔗 Universal Integration
- Works seamlessly with popular monitoring tools (Prometheus, Grafana, New Relic, Datadog, Zabbix, etc.)
- Technology-agnostic design — integrate with *any* observability solution
- REST API and webhook support for custom workflows

### 📊 Intelligent Alert Management
- Collect alerts from multiple observability systems
- Smart correlation and deduplication to reduce noise
- Flexible alert routing based on severity, source, and tags
- Alert suppression and maintenance windows

### 🚨 Incident Management
- Transform alerts into actionable incidents
- Incident analysis to reduce troubleshooting time
- Automated AI-generated incident reports (e.g., postmortems)
- Track key metrics (MTTR, MTTA, SLA compliance)

### 👥 On-Call Management
- Customizable on-call rotations and schedules
- Automated escalations with configurable timeouts
- Easy handoff management and shift swapping

### 📱 Flexible Notifications
- Multi-channel notifications: SMS, phone calls, email, Slack, Microsoft Teams, Telegram, etc.
- Rich email formatting for better context
- Endpoint verification flow to ensure no critical alert is lost

### 📈 Custom Status Pages
- Public or private branded status pages
- Real-time incident updates
- Historical uptime reporting
- Subscriber notifications

### 🛠 Advanced Features
- Full incident lifecycle tracking and post-mortems
- Alert analytics and trend reporting
- Custom dashboards and widgets
- Role-Based Access Control (RBAC)

### Reports 
- SLA Report for each service for a long period of time
- Service issues reports 
- 
---

## 👨‍💻 Who Uses Skylogs?

### Network & Datacenter Teams
- Strong integration with Zabbix and similar tools
- Endpoint assurance for reliable critical alert delivery  

### DevOps Engineers
- Centralize alerts from multiple monitoring platforms
- Reduce alert fatigue with intelligent routing
- Simplify on-call scheduling and escalation  

### Site Reliability Engineers (SREs)
- Improve MTTD and MTTR  
- Enforce reliable alerting strategies  
- Analyze incident trends and patterns  
- Maintain and track SLOs  

### Development Teams
- Real-time notifications on application issues  
- Team/project-specific alert preferences  
- Integration with development workflows  
- Monitor application performance and health  

### Security Teams
- Integrate with Splunk, ELK stack, and SIEMs  
- Manage security alerts and incidents  
- Coordinate SOC response efforts  

### Engineering Managers
- Visibility into team workload and on-call burden  
- Track incident response metrics and KPIs  
- Ensure escalation coverage and accountability  
- Generate executive-ready reports  

---

## 🎯 Why Choose Skylogs?

### 🔧 Shared Responsibility
- Collaborative workflows across DevOps, SRE, SecOps, and Dev teams  
- Clear team-based alert ownership  

### 🎨 Personalization
- Tailored notification preferences per user  
- Custom routing rules and escalation policies  
- Personalized dashboards and alerts  

### 🚀 Easy Integration
- Plug-and-play setup with major observability tools  
- REST API and webhooks for custom integrations  
- Pre-built connectors for common platforms  

### 📊 Data-Driven Insights
- Detailed analytics on alerts and incidents  
- SLA, SLO, and KPI tracking  
- Historical data for capacity planning  
- Custom reporting dashboards  

### 🔒 Enterprise-Ready
- RBAC and fine-grained permissions  
- SSO integration (coming soon)  
- SOC 2 Type II compliance (in progress)  
- Full audit logs and retention policies  

---

## 🐳 Quick Start with Docker

### Prerequisites
- Docker & Docker Compose installed  
- Git  

### Installation

```bash
# Clone the repository
git clone https://github.com/skylogsio/skylogs.git
cd skylogs

docker-compose up -d --build


```

## 📚 Documentation

Comprehensive documentation is available in the following sections:

- [Installation Guide](docs/installation.md) - Detailed setup instructions
- [Configuration](docs/configuration.md) - Environment and application configuration
- [API Documentation](docs/api.md) - REST API endpoints and examples
- [Integration Guide](docs/integrations.md) - Third-party tool integrations
- [User Guide](docs/user-guide.md) - End-user documentation
- [Administrator Guide](docs/admin-guide.md) - System administration
- [Developer Guide](docs/developer-guide.md) - Contributing and development setup
- [Deployment Guide](docs/deployment.md) - Production deployment strategies
- [Troubleshooting](docs/troubleshooting.md) - Common issues and solutions
- [Changelog](docs/changelog.md) - Version history and updates
- [Contributing Guidelines](docs/contributing.md) - How to contribute to the project
- [Security Policy](docs/security.md) - Security guidelines and reporting
- [FAQ](docs/faq.md) - Frequently asked questions

## 🤝 Contributing

We welcome contributions from the community! Please read our [Contributing Guidelines](docs/contributing.md) to get started.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/skylogsio/skylogs/issues)
- **Discussions**: [GitHub Discussions](https://github.com/skylogsio/skylogs/discussions)
- **Email**: support@skylogs.io

## 🌟 Star History

[![Star History Chart](https://api.star-history.com/svg?repos=skylogsio/skylogs&type=Date)](https://star-history.com/#skylogsio/skylogs&Date)

---

<div align="center">
  <p>Made with ❤️ by the Skylogs team</p>
  <p>
    <a href="https://skylogs.io">Website</a> •
  </p>
</div>
