# 📘 Standard Blackbox Exporter Alerts  
### _Complete Documentation in Markdown_

This document describes all common and recommended alerts for **Prometheus Blackbox Exporter** including:  
- HTTP probes  
- TCP probes  
- ICMP (Ping) probes  
- TLS/SSL certificate checks  
- DNS resolution checks

These alerts are widely used in **kube-prometheus**, Blackbox community dashboards, and SRE production environments.

---

# 🟦 1. Uptime / Availability Alerts

## 🔹 BlackboxProbeFailed
**Meaning:** The probe result is `failure` (`probe_success = 0`).  
**Triggered when:**  
- HTTP endpoint returns non-2xx/3xx  
- TCP port not reachable  
- ICMP ping lost  
- DNS failure  
**Fix:**  
- Verify endpoint availability  
- Check firewall, routing  
- Restart service  
- Verify domain or DNS configuration

---

## 🔹 TargetDown (HTTP/TCP/ICMP)
**Meaning:** Blackbox cannot reach the target for a prolonged period.  
**Cause:**  
- Service is down  
- Network outage  
- Node unreachable  
**Fix:**  
- Restart application  
- Check network path  
- Verify LB/ingress/Firewall rules

---

# 🟦 2. Latency Alerts

## 🔹 BlackboxProbeLatencyHigh
**Meaning:** Probe response time too high.  
**Measured by:** `probe_duration_seconds`  
**Common thresholds:**  
- Warning: > 0.5s  
- Critical: > 1s  
**Cause:**  
- Application slow  
- Network congestion  
- Load balancer issues  
**Fix:**  
- Optimize backend  
- Reduce latency through caching  
- Investigate network performance

---

## 🔹 BlackboxTCPConnectSlow
**Meaning:** TCP handshake takes too long.  
**Cause:**  
- Slow network  
- SYN retries  
- Overloaded server  
**Fix:**  
- Improve server capacity  
- Add load balancer  
- Fix network bottlenecks

---

# 🟦 3. HTTP Endpoint Alerts

## 🔹 BlackboxHTTPStatusCodeMismatch
**Meaning:** The actual HTTP response code does not match the expected one.  
Example: Expect 200 but service returns 500.  
**Cause:**  
- Service error  
- Bad gateway  
- Unhealthy backend  
**Fix:**  
- Check logs  
- Validate upstream servers

---

## 🔹 BlackboxSSLInvalidHTTPResponse
**Meaning:** HTTPS endpoint returns corrupt or incomplete SSL messages.  
**Fix:**  
- Fix TLS termination  
- Check reverse proxy  
- Validate certificate chain

---

# 🟦 4. SSL / TLS Certificate Alerts

## 🔹 BlackboxSSLWillExpireSoon
**Meaning:** TLS certificate expiration is approaching.  
**Threshold examples:**  
- Warning: 14 days  
- Critical: < 7 days  
**Cause:**  
- Certificate not rotated  
**Fix:**  
- Renew certificate  
- Update secret/ingress  
- Reload web server

---

## 🔹 BlackboxSSLExpired
**Meaning:** TLS certificate already expired.  
**Fix:**  
- Replace certificate immediately  
- Restart service  
- Update DNS/ingress if needed

---

## 🔹 BlackboxSSLInvalid
**Meaning:** TLS handshake fails.  
Possible causes:  
- Wrong CN/SAN  
- Invalid certificate chain  
- Wrong TLS version  
**Fix:**  
- Fix certificate chain  
- Configure TLS version correctly

---

# 🟦 5. DNS Resolution Alerts

## 🔹 BlackboxDNSLookupFailed
**Meaning:** DNS resolution for target domain failed.  
**Cause:**  
- Domain missing  
- DNS zone failure  
- DNS server outage  
**Fix:**  
- Check authoritative DNS  
- Validate zone configuration  
- Fix DNS upstream servers

---

## 🔹 BlackboxDNSSlow
**Meaning:** DNS lookup duration is too high.  
**Cause:**  
- Slow resolver  
- Misconfigured DNS cache  
**Fix:**  
- Use faster resolvers (8.8.8.8 / 1.1.1.1)  
- Improve DNS server performance

---

# 🟦 6. TCP Alerts

## 🔹 BlackboxTCPProbeFailed
**Meaning:** Target TCP port unreachable.  
**Fix:**  
- Check port binding  
- Restart service  
- Fix firewall or security group

---

## 🔹 BlackboxTCPConnectionReset
**Meaning:** Connection reset during handshake.  
**Cause:**  
- Service overloaded  
- Load balancer resetting connections  
**Fix:**  
- Check logs  
- Increase backend capacity

---

# 🟦 7. ICMP (Ping) Alerts

## 🔹 BlackboxPingFailed
**Meaning:** ICMP echo request failed.  
**Fix:**  
- Check firewall  
- Check host availability  
- Validate routing

---

## 🔹 BlackboxPingPacketLoss
**Meaning:** Packet loss percentage is high.  
**Fix:**  
- Fix network congestion  
- Check faulty NIC or switch  
- Validate routing

---

# 🟦 8. Redirect Issues

## 🔹 BlackboxHTTPTooManyRedirects
**Meaning:** Endpoint is redirecting endlessly.  
**Cause:** Circular redirects.  
**Fix:**  
- Fix web server config  
- Remove bad redirect chains

---

## 🔹 BlackboxHTTPRedirectMismatch
**Meaning:** Expected redirect (301/302) is missing or incorrect.  
**Fix:**  
- Fix application routing  
- Correct ingress/nginx rules

---

# 🟦 9. Content Matching Alerts

## 🔹 BlackboxHTTPContentMissing
**Meaning:** Expected text or regex in HTTP page is *not* found.  
Used for:  
- API health checks  
- Login pages  
- Service banners  
**Fix:**  
- Fix app response  
- Validate API output

---

## 🔹 BlackboxHTTPContentUnexpected
**Meaning:** Page contains unexpected content (e.g., “error”, “database down”).  
**Fix:**  
- Validate backend  
- Fix upstream issues

---

# 🟦 10. SSL Fingerprint / Chain / Protocol Issues

## 🔹 BlackboxSSLChainInvalid
**Meaning:** Incorrect intermediate certificates.  
**Fix:**  
- Install correct chain  
- Update certificate bundle

---

## 🔹 BlackboxSSLProtocolUnsupported
**Meaning:** Target only supports old TLS versions (e.g., TLS 1.0).  
**Fix:**  
- Enable TLS 1.2/1.3  
- Update server configuration

---

# 🟦 Bonus: Recommended Blackbox Alerting Bundle
Most SRE teams group alerts into layers:

### 🟥 Critical
- Probe failed  
- SSL expired  
- DNS lookup failed  
- TCP port unreachable  
- Ping down  

### 🟧 Warning
- SSL will expire soon  
- High latency  
- Packet loss  
- Slow DNS  

### 🟨 Info (optional)
- Protocol downgraded  
- Unexpected page content  
- Redirect count high  

---

# 📌 Want More?

Available on request:

- **Full PromQL expressions** for every Blackbox alert  
- **PrometheusRule YAML files** for kube-prometheus-stack  
- **Grafana dashboards for Blackbox**  
- **Combined documentation PDF or DOCX**  
- **Monitoring architecture diagrams**

Just ask:

`Give me full Blackbox PromQL rules`  
or  
`Generate Blackbox PrometheusRule YAML`

