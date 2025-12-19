package config

import (
	"time"

	"gopkg.in/yaml.v3"
	"os"
)

type Config struct {
	DC string `yaml:"dc"`

	Peer struct {
		Name string `yaml:"name"`
		URL  string `yaml:"url"`
	} `yaml:"peer"`

	Heartbeat struct {
		Interval time.Duration `yaml:"interval"`
		Timeout  time.Duration `yaml:"timeout"`
	} `yaml:"heartbeat"`

	Alert struct {
		Webhook string `yaml:"webhook"`
	} `yaml:"alert"`

	Server struct {
		Listen string `yaml:"listen"`
	} `yaml:"server"`
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

