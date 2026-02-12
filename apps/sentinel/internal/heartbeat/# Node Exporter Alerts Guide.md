# Node Exporter Alerts Guide

A comprehensive guide for monitoring system metrics using Node Exporter in Prometheus/Alertmanager. Alerts are categorized by subsystem.

---

## 1. CPU Alerts

| Alert Name            | Description                               | PromQL Example                                                                         | Severity |
| --------------------- | ----------------------------------------- | -------------------------------------------------------------------------------------- | -------- |
| `NodeCPUHigh`         | CPU usage is consistently high            | `100 - (avg by (instance) (rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100) > 85` | critical |
| `NodeCPULoadHigh`     | System load is high compared to CPU cores | `node_load1 / count(node_cpu_seconds_total{mode="idle"} / 100) > 1.5`                  | warning  |
| `NodeCPULoadVeryHigh` | Load is much higher than capacity         | `node_load5 / count(node_cpu_seconds_total{mode="idle"} / 100) > 2`                    | critical |

---

## 2. Memory Alerts

| Alert Name           | Description             | PromQL Example                                                         | Severity |
| -------------------- | ----------------------- | ---------------------------------------------------------------------- | -------- |
| `NodeMemoryLow`      | Available memory is low | `(node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes) < 0.15` | critical |
| `NodeMemorySwapHigh` | Swap usage is high      | `(node_memory_SwapUsed_bytes / node_memory_SwapTotal_bytes) > 0.20`    | warning  |

---

## 3. Disk Alerts

| Alert Name           | Description                  | PromQL Example                                    | Severity                                              |                    |          |
| -------------------- | ---------------------------- | ------------------------------------------------- | ----------------------------------------------------- | ------------------ | -------- |
| `NodeDiskFull`       | Disk usage exceeds threshold | `(node_filesystem_avail_bytes{fstype!~"tmpfs      | overlay"} / node_filesystem_size_bytes{fstype!~"tmpfs | overlay"}) < 0.10` | critical |
| `NodeDiskAlmostFull` | Disk usage approaching full  | `(node_filesystem_avail_bytes{fstype!~"tmpfs      | overlay"} / node_filesystem_size_bytes{fstype!~"tmpfs | overlay"}) < 0.20` | warning  |
| `NodeDiskIOHigh`     | High disk I/O (write/read)   | `rate(node_disk_io_time_seconds_total[5m]) > 0.9` | warning                                               |                    |          |

---

## 4. Network Alerts

| Alert Name                 | Description                                | PromQL Example                                                                                                               | Severity |
| -------------------------- | ------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------- | -------- |
| `NodeNetworkErrorRateHigh` | High network error rate                    | `rate(node_network_receive_errs_total[5m]) > 0 or rate(node_network_transmit_errs_total[5m]) > 0`                            | warning  |
| `NodeNetworkDropHigh`      | High packet drop                           | `rate(node_network_receive_drop_total[5m]) > 0 or rate(node_network_transmit_drop_total[5m]) > 0`                            | warning  |
| `NodeNetworkBandwidthHigh` | Network utilization high (link saturating) | `(rate(node_network_transmit_bytes_total[5m]) + rate(node_network_receive_bytes_total[5m])) > 0.9 * <interface_speed_bytes>` | warning  |

---

## 5. Filesystem & Inodes Alerts

| Alert Name             | Description             | PromQL Example                         | Severity                                              |                                                   |                   |          |
| ---------------------- | ----------------------- | -------------------------------------- | ----------------------------------------------------- | ------------------------------------------------- | ----------------- | -------- |
| `NodeInodesFull`       | Inodes usage high       | `(node_filesystem_files{fstype!~"tmpfs | overlay"} - node_filesystem_files_free{fstype!~"tmpfs | overlay"}) / node_filesystem_files{fstype!~"tmpfs | overlay"} > 0.85` | critical |
| `NodeInodesAlmostFull` | Inodes approaching full | `(node_filesystem_files{fstype!~"tmpfs | overlay"} - node_filesystem_files_free{fstype!~"tmpfs | overlay"}) / node_filesystem_files{fstype!~"tmpfs | overlay"} > 0.70` | warning  |

---

## 6. System & Hardware Alerts

| Alert Name               | Description                    | PromQL Example                            | Severity |
| ------------------------ | ------------------------------ | ----------------------------------------- | -------- |
| `NodeFilesystemReadOnly` | Filesystem went read-only      | `node_filesystem_readonly == 1`           | critical |
| `NodeTempHigh`           | CPU or system temperature high | `node_thermal_zone_temp > 80`             | critical |
| `NodeBootTimeChanged`    | Node reboot detected           | `changes(node_boot_time_seconds[1h]) > 0` | warning  |

---

## 7. Other Useful Alerts

| Alert Name                | Description                 | PromQL Example                                  | Severity |
| ------------------------- | --------------------------- | ----------------------------------------------- | -------- |
| `NodeCollectorDown`       | Node exporter not reachable | `up{job="node_exporter"} == 0`                  | critical |
| `NodeHighContextSwitches` | Too many context switches   | `rate(node_context_switches_total[5m]) > 10000` | warning  |
| `NodeHighInterrupts`      | High interrupts per second  | `rate(node_intr_total[5m]) > 10000`             | warning  |

---

## Best Practices

1. **Label Alerts** – include `instance` and `job` labels to know exactly which node is affected.
2. **Severity Levels** – use `warning` for early detection and `critical` for immediate attention.
3. **Alert Duration** – avoid firing on short spikes; use `[5m]` or `[10m]` ranges.
4. **Grouping Alerts** – group similar alerts in Alertmanager to reduce noise.
5. **Runbooks** – provide clear remediation steps for each alert.
 