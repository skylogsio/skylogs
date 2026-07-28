package app

import (
	"RAFT_Service/internal/api/handler"
	"RAFT_Service/internal/api/routes"
	"RAFT_Service/internal/model"
	"RAFT_Service/internal/service"
	"os"
	"strconv"

	"github.com/gin-gonic/gin"
	"github.com/rs/zerolog/log"
)

type App struct {
	Config *model.Config

	Service *service.NodeService

	Router *gin.Engine
}

func New() (*App, error) {
	//create config -> then create node -> create node service -> return as app
	config := model.ParseConfig()
	//Check for data existence or new cluster creation
	if hasExistingData(config.DataDir) {
		log.Info().
			Str("data_dir", config.DataDir).
			Msg("existing raft data found, ignoring bootstrap and join flags")

		config.JoinAddress = "http://" + os.Getenv("VIP") + ":" + strconv.Itoa(config.HTTPPort)
		config.Bootstrap = false
	}

	// Create and start node
	node, err := service.NewNode(config)
	if err != nil {
		return nil, err
	}
	// create service
	nodeService := service.NewNodeService(node)
	// create API handler
	h := handler.NewHandler(nodeService)
	// create router and bind addresses
	// router := gin.Default()       This will show logs of all requests!
	router := gin.New()
	router.Use(gin.LoggerWithConfig(gin.LoggerConfig{
		SkipPaths: []string{
			"/leader",
			"/health",
		},
	}))
	router.Use(gin.Recovery())
	routes.HomeGroupRoutes(router, h)

	return &App{

		Config: config,

		Service: nodeService,

		Router: router,
	}, nil
}

func hasExistingData(dir string) bool {
	entries, err := os.ReadDir(dir)
	if err != nil {
		return false
	}

	return len(entries) > 0
}
