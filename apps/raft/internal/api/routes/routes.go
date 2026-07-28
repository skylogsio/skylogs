package routes

import (
	"RAFT_Service/internal/api/handler"

	"github.com/gin-gonic/gin"
)

func HomeGroupRoutes(e *gin.Engine, h *handler.Handler) {
	homeGroup := e.Group("/")
	//homeGroup.Use(handler.SessionMiddleware())
	{
		homeGroup.POST("/set", h.HandleSet)
		homeGroup.GET("/get", h.HandleGet)
		homeGroup.POST("/join", h.HandleJoin)
		homeGroup.GET("/status", h.HandleStatus)
		homeGroup.GET("/leader", h.HandleLeader)
		homeGroup.POST("", h.HandleDelete)
	}

}
