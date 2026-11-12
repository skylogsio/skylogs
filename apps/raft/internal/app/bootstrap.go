package app

import (
	"os"
	"strconv"

	"RAFT_Service/internal/api/handler"
	"RAFT_Service/internal/api/routes"
	"RAFT_Service/internal/model"
	"RAFT_Service/internal/service"

	"github.com/gin-gonic/gin"
	"github.com/rs/zerolog/log"
)

// App holds application configuration, services, and HTTP routing.
type App struct {
	Config  *model.Config
	Service *service.NodeService
	Router  *gin.Engine
}

// New initializes the application and its Raft-backed services.
func New() (*App, error) {
	// Load configuration.
	config := model.ParseConfig()

	// Check for existing Raft data.
	if hasExistingData(config.DataDir) {
		log.Info().
			Str("data_dir", config.DataDir).
			Msg("existing raft data found, ignoring bootstrap and join flags")

		config.JoinAddress = "http://" + os.Getenv("VIP") + ":" + strconv.Itoa(config.HTTPPort)
		config.Bootstrap = false
	}

	// Create and start the Raft node.
	node, err := service.NewNode(config)
	if err != nil {
		return nil, err
	}

	// Create application service and handlers.
	nodeService := service.NewNodeService(node)
	h := handler.NewHandler(nodeService)

	// Create Gin router.
	router := gin.New()

	router.Use(gin.LoggerWithConfig(gin.LoggerConfig{
		SkipPaths: []string{
			"/leader",
			"/health",
		},
	}))

	router.Use(gin.Recovery())

	// Register existing routes and the new cluster status endpoint.
	routes.HomeGroupRoutes(router, h, node.ClusterStatus)

	return &App{
		Config:  config,
		Service: nodeService,
		Router:  router,
	}, nil
}

// hasExistingData checks whether a Raft data directory contains files.
func hasExistingData(dir string) bool {
	entries, err := os.ReadDir(dir)
	if err != nil {
		return false
	}

	return len(entries) > 0
}
