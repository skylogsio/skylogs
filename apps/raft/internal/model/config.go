package model

import (
	"flag"
	"fmt"
	"os"
)

type Config struct {
	BindAddress      string
	AdvertiseAddress string
	RaftPort         int
	HTTPPort         int
	DataDir          string
	Bootstrap        bool
	JoinAddress      string
	NodeID           string
	// Optional sidecar → local app push after each committed FSM change.
	NotifyURL    string
	NotifySecret string
	NotifyHeader string
}

func ParseConfig() *Config {
	config := &Config{}

	flag.StringVar(&config.BindAddress, "bind-address", "0.0.0.0", "Bind address for Raft and HTTP")
	flag.StringVar(
		&config.AdvertiseAddress,
		"advertise-address",
		"",
		"Address other nodes use to reach this node",
	)
	flag.IntVar(&config.RaftPort, "raft-port", 7000, "Port for Raft TCP transport")
	flag.IntVar(&config.HTTPPort, "http-port", 8000, "Port for HTTP API")
	flag.StringVar(&config.DataDir, "data-dir", "", "Data directory for Raft (required)")
	flag.BoolVar(&config.Bootstrap, "bootstrap", false, "Bootstrap a new cluster")
	flag.StringVar(&config.JoinAddress, "join", "", "Address of leader to join (e.g., http://127.0.0.1:8000)")
	flag.StringVar(&config.NodeID, "node-id", "", "Unique node ID (required)")
	// New ones for notifier
	flag.StringVar(&config.NotifyURL, "notify-url", "", "POST committed key/value changes here (optional)")
	flag.StringVar(&config.NotifySecret, "notify-secret", "", "Shared secret sent on notifier requests")
	flag.StringVar(&config.NotifyHeader, "notify-header", "X-Raft-Notify-Secret", "Header name for notifier-secret")
	flag.Parse()

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
		os.Exit(1)
	}
	return config
}
