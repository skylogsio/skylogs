package config

import (
	"os"
	"strings"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
	"gopkg.in/yaml.v3"
)

type Config struct {
	Server struct {
		Listen string `yaml:"listen"`
	} `yaml:"server"`

	Sentinel struct {
		ID               string `yaml:"id"`
		SelfInstanceName string `yaml:"self_instance_name"`
		Role             string `yaml:"role"`
	} `yaml:"sentinel"`

	// MainSentinel is read by role=agent peers: periodically pull peers from base_url and cache locally.
	MainSentinel struct {
		BaseURL         string        `yaml:"base_url"`
		PeersPath       string        `yaml:"peers_path"`
		RefreshInterval time.Duration `yaml:"refresh_interval"`
		CacheFile       string        `yaml:"cache_file"`
		// Name is the alert instance label when agents watch main (default "main").
		Name string `yaml:"name"`
		// SentinelID optional; used to dedupe if main is already in the pulled peer list.
		SentinelID string `yaml:"sentinel_id"`
	} `yaml:"main_sentinel"`

	// Peers lists all Sentinel heartbeat URLs when role=main (authoritative). Agents may omit or bootstrap here.
	Peers []discovery.Peer `yaml:"peers"`

	Heartbeat struct {
		Interval  time.Duration `yaml:"interval"`
		Timeout   time.Duration `yaml:"timeout"`
		FailAfter time.Duration `yaml:"fail_after"`
	} `yaml:"heartbeat"`

	Alert struct {
		WebhookUrl    string        `yaml:"webhook_url"`
		Token         string        `yaml:"token"`
		RetryInterval time.Duration `yaml:"retry_interval"`
	} `yaml:"alert"`

	Security struct {
		SharedSecret string        `yaml:"shared_secret"`
		AllowedDrift time.Duration `yaml:"allowed_drift"`
	} `yaml:"security"`
}

func Load(path string) (*Config, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	var cfg Config
	if err := yaml.Unmarshal(data, &cfg); err != nil {
		return nil, err
	}
	applyEnv(&cfg)
	return &cfg, nil
}

func (c *Config) sentinelRoleNorm() string {
	return strings.ToLower(strings.TrimSpace(c.Sentinel.Role))
}

// IsMainRole treats empty / main / primary as the cluster-authority node (hosts full peers list).
func (c *Config) IsMainRole() bool {
	switch c.sentinelRoleNorm() {
	case "", "main", "primary":
		return true
	default:
		return false
	}
}

// AgentPullEnabled is true for non-main nodes that set main_sentinel.base_url (e.g. role agent or secondary).
func (c *Config) AgentPullEnabled() bool {
	if c.IsMainRole() {
		return false
	}
	return strings.TrimSpace(c.MainSentinel.BaseURL) != ""
}

func applyEnv(cfg *Config) {
	if v := os.Getenv("SENTINEL_ID"); v != "" {
		cfg.Sentinel.ID = v
	}
	if v := os.Getenv("SENTINEL_ROLE"); v != "" {
		cfg.Sentinel.Role = v
	}
	if v := os.Getenv("SENTINEL_SELF_INSTANCE_NAME"); v != "" {
		cfg.Sentinel.SelfInstanceName = v
	}
	if v := os.Getenv("SENTINEL_LISTEN"); v != "" {
		cfg.Server.Listen = v
	}
	if v := os.Getenv("ALERT_WEBHOOK_URL"); v != "" {
		cfg.Alert.WebhookUrl = v
	}
	if v := os.Getenv("SENTINEL_ALERT_TOKEN"); v != "" {
		cfg.Alert.Token = v
	}
	if v := os.Getenv("SHARED_SECRET"); v != "" {
		cfg.Security.SharedSecret = v
	}
	if v := os.Getenv("HEARTBEAT_INTERVAL"); v != "" {
		if d, err := time.ParseDuration(v); err == nil {
			cfg.Heartbeat.Interval = d
		}
	}
	if v := os.Getenv("HEARTBEAT_TIMEOUT"); v != "" {
		if d, err := time.ParseDuration(v); err == nil {
			cfg.Heartbeat.Timeout = d
		}
	}
	if v := os.Getenv("HEARTBEAT_FAIL_AFTER"); v != "" {
		if d, err := time.ParseDuration(v); err == nil {
			cfg.Heartbeat.FailAfter = d
		}
	}
	if v := os.Getenv("MAIN_SENTINEL_URL"); v != "" {
		cfg.MainSentinel.BaseURL = v
	}
	if v := os.Getenv("MAIN_SENTINEL_PEERS_PATH"); v != "" {
		cfg.MainSentinel.PeersPath = v
	}
	if v := os.Getenv("CLUSTER_REFRESH_INTERVAL"); v != "" {
		if d, err := time.ParseDuration(v); err == nil {
			cfg.MainSentinel.RefreshInterval = d
		}
	}
	if v := os.Getenv("PEERS_CACHE_FILE"); v != "" {
		cfg.MainSentinel.CacheFile = v
	}
	if v := os.Getenv("MAIN_SENTINEL_NAME"); v != "" {
		cfg.MainSentinel.Name = v
	}
	if v := os.Getenv("MAIN_SENTINEL_ID"); v != "" {
		cfg.MainSentinel.SentinelID = v
	}
}
