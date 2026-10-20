package routes

import (
	"context"

	"RAFT_Service/internal/api/handler"
	"RAFT_Service/internal/model"

	"github.com/gin-gonic/gin"
)

// HomeGroupRoutes registers application and cluster status endpoints.
func HomeGroupRoutes(
	e *gin.Engine,
	h *handler.Handler,
	getClusterStatus func(context.Context) model.ClusterObservation,
) {
	homeGroup := e.Group("/")

	{
		homeGroup.POST("/set", h.HandleSet)
		homeGroup.GET("/get", h.HandleGet)
		homeGroup.POST("/join", h.HandleJoin)
		homeGroup.GET("/status", h.HandleStatus)
		homeGroup.GET("/leader", h.HandleLeader)
		homeGroup.POST("", h.HandleDelete)

		// Return an on-demand cluster observation.
		homeGroup.GET(
			"/cluster/status",
			handler.ClusterStatusHandler(getClusterStatus),
		)
	}
}
