package model

import (
    "flag"
    "fmt"
    "net/http"
    "os"
    "strings"
    "time"
)

// Config holds application, Raft, notifier, and alert monitoring settings.
type Config struct {
    BindAddress      string
    AdvertiseAddress string
    RaftPort         int
    HTTPPort         int
    DataDir          string
    Bootstrap        bool
    JoinAddress      string
    NodeID           string

    NotifyURL    string
    NotifySecret string
    NotifyHeader string

    ClusterReportURL       string
    ClusterCheckInterval   time.Duration
    ClusterCandidateGrace  time.Duration
    ClusterAlertFireURL    string
    ClusterAlertResolveURL string
    ClusterReportHeaders   http.Header
    RaftLogInterval        time.Duration
}

// ParseConfig loads application flags and Raft monitoring environment variables.
func ParseConfig() *Config {
    config := &Config{}

    flag.StringVar(&config.BindAddress, "bind-address", "0.0.0.0", "Bind address for Raft and HTTP")
    flag.StringVar(&config.AdvertiseAddress, "advertise-address", "", "Address other nodes use to reach this node")
    flag.IntVar(&config.RaftPort, "raft-port", 7000, "Port for Raft TCP transport")
    flag.IntVar(&config.HTTPPort, "http-port", 8000, "Port for HTTP API")
    flag.StringVar(&config.DataDir, "data-dir", "", "Data directory for Raft (required)")
    flag.BoolVar(&config.Bootstrap, "bootstrap", false, "Bootstrap a new cluster")
    flag.StringVar(&config.JoinAddress, "join", "", "Address of leader to join")
    flag.StringVar(&config.NodeID, "node-id", "", "Unique node ID (required)")

    flag.StringVar(&config.NotifyURL, "notify-url", "", "POST committed key/value changes here")
    flag.StringVar(&config.NotifySecret, "notify-secret", "", "Shared secret for notifier requests")
    flag.StringVar(&config.NotifyHeader, "notify-header", "X-Raft-Notify-Secret", "Notifier secret header name")

    flag.Parse()

    config.ClusterReportURL = os.Getenv("RAFT_CLUSTER_REPORT_URL")
    config.ClusterAlertFireURL = os.Getenv("RAFT_CLUSTER_ALERT_FIRE_URL")
    config.ClusterAlertResolveURL = os.Getenv("RAFT_CLUSTER_ALERT_RESOLVE_URL")
    config.ClusterCheckInterval = parseDurationEnv("RAFT_CLUSTER_CHECK_INTERVAL", 5*time.Second)
    config.ClusterCandidateGrace = parseDurationEnv("RAFT_CLUSTER_CANDIDATE_GRACE", 5*time.Second)
    config.RaftLogInterval = parseDurationEnv("RAFT_LOG_INTERVAL", 5*time.Minute)
    config.ClusterReportHeaders = readClusterReportHeaders()

    if config.DataDir == "" {
        fmt.Println("Error: --data-dir is required")
        flag.Usage()
        os.Exit(1)
    }

    if config.NodeID == "" {
        fmt.Println("Error: --node-id is required")
        flag.Usage()
        os.Exit(1)
    }

    if config.AdvertiseAddress == "" {
        fmt.Println("Error: --advertise-address is required")
        flag.Usage()
        os.Exit(1)
    }

    if (config.ClusterAlertFireURL == "") != (config.ClusterAlertResolveURL == "") {
        fmt.Fprintln(os.Stderr, "both RAFT_CLUSTER_ALERT_FIRE_URL and RAFT_CLUSTER_ALERT_RESOLVE_URL must be configured")
        os.Exit(1)
    }

    return config
}

// parseDurationEnv reads a positive duration or uses its default.
func parseDurationEnv(name string, fallback time.Duration) time.Duration {
    value := os.Getenv(name)
    if value == "" {
        return fallback
    }

    duration, err := time.ParseDuration(value)
    if err != nil || duration <= 0 {
        fmt.Fprintf(os.Stderr, "invalid %s: %q\n", name, value)
        os.Exit(1)
    }

    return duration
}

// readClusterReportHeaders loads arbitrary HTTP headers from RAFT-prefixed variables.
func readClusterReportHeaders() http.Header {
    const prefix = "RAFT_CLUSTER_REPORT_HEADER_"
    headers := make(http.Header)

    for _, entry := range os.Environ() {
        name, value, ok := strings.Cut(entry, "=")
        if !ok || !strings.HasPrefix(name, prefix) {
            continue
        }

        headerName := strings.TrimPrefix(name, prefix)
        headerName = strings.ReplaceAll(headerName, "_", "-")
        headerName = http.CanonicalHeaderKey(headerName)

        if headerName == "" || headerName == "Host" || headerName == "Content-Length" {
            continue
        }
        headers.Set(headerName, value)
    }

    return headers
}
