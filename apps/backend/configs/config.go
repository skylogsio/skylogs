package configs

import (
	"fmt"
	"github.com/knadh/koanf/parsers/yaml"
	"github.com/knadh/koanf/providers/file"
	"github.com/knadh/koanf/v2"
	"log"
	"path/filepath"
	"runtime"
)

var ProjectRootPath = ConfigsDirPath() + "/../../../"

type Env int

const (
	Development Env = iota
	Production
)

var CurrentEnv Env = Development

type (
	Config struct {
		AppName string `koanf:"app_name"`
		Mongo   Mongo  `koanf:"mongo"`
		Redis   Redis  `koanf:"redis"`
		Email   Email  `koanf:"email"`
		HTTP    HTTP   `koanf:"http"`
		Auth    Auth   `koanf:"auth"`
		Logger  Logger `koanf:"logger"`
		Nats    Nats   `koanf:"nats"`
	}
	Nats struct {
		Url string `koanf:"url"`
	}

	Auth struct {
		SecretKey string `koanf:"secret"`
	}

	HTTP struct {
		Host string `koanf:"host"`
		Port int    `koanf:"port"`
		Cors Cors   `koanf:"cors"`
	}

	Cors struct {
		AllowOrigins []string `koanf:"allow_origins"`
	}

	Redis struct {
		Host     string `koanf:"host"`
		Username string `koanf:"username"`
		Password string `koanf:"password"`
		Port     int    `koanf:"port"`
		DB       int    `koanf:"db"`
	}

	Mongo struct {
		Host       string `koanf:"host"`
		Username   string `koanf:"username"`
		Password   string `koanf:"password"`
		Port       int    `koanf:"port"`
		DBName     string `koanf:"db_name"`
		AuthSource string `koanf:"auth_source"`
	}

	Email struct {
		SenderEmail string `koanf:"sender_email"`
		Password    string `koanf:"password"`
		Host        string `koanf:"host"`
		Port        string `koanf:"port"`
	}

	Logger struct {
		Filename   string   `koanf:"filename"`
		LogLevel   string   `koanf:"level"`
		Targets    []string `koanf:"targets"`
		MaxSize    int      `koanf:"max_size"`
		MaxBackups int      `koanf:"max_backups"`
		Compress   bool     `koanf:"compress"`
	}
)

func ConfigsDirPath() string {
	_, f, _, ok := runtime.Caller(0)
	if !ok {
		panic("Error in generating env dir")
	}

	return filepath.Dir(f)
}

var Configs = &Config{}

func (c *Config) Load() {
	var fileName string

	// Load KAVKA ENV
	//env := strings.ToLower(os.Getenv("KAVKA_ENV"))

	fileName = "config.yaml"
	//if len(strings.TrimSpace(env)) == 0 || env == "development" {
	//	CurrentEnv = Development
	//	fileName = "config.development.yml"
	//} else if env == "production" {
	//	CurrentEnv = Production
	//	fileName = "config.production.yml"
	//} else {
	//	log.Fatalln(errors.New("Invalid env value set for variable KAVKA_ENV: " + env))
	//}

	// Load YAML configs
	k := koanf.New(ProjectRootPath)
	if err := k.Load(file.Provider(fmt.Sprintf("%s", fileName)), yaml.Parser()); err != nil {
		log.Fatalf("error loading config: %v", err)
	}

	if err := k.Unmarshal("", Configs); err != nil {
		log.Fatalf("error unmarshaling config: %v", err)
	}

}
