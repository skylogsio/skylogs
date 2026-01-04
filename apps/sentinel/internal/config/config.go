package config

import (
	"os"
	"time"

	"gopkg.in/yaml.v3"
)

type Config struct {
	Heartbeat struct {
		TargetURL string        `yaml:"target_url"`
		Interval  time.Duration `yaml:"interval"`
		Timeout   time.Duration `yaml:"timeout"`
		FailAfter time.Duration `yaml:"fail_after"`
	} `yaml:"heartbeat"`

	Server struct {
		Listen string `yaml:"listen"`
	} `yaml:"server"`

	Sentinel struct {
		Id   string `yaml:"id"`
		Role string `yaml:"role"`
	} `yaml:"sentinel"`

	Alert struct {
		WebhookUrl    string        `yaml:"webhook_url"`
		Token         string        `yaml:"token"`
		Instance      string        `yaml:"instance"`
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

	return &cfg, nil
}
