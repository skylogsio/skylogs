package handler

import (
	"context"
	"net/http"
	"time"

	"RAFT_Service/internal/model"

	"github.com/gin-gonic/gin"
)

// ClusterStatusHandler returns a handler for on-demand cluster observations.
func ClusterStatusHandler(getStatus func(context.Context) model.ClusterObservation) gin.HandlerFunc {
	return func(c *gin.Context) {
		ctx, cancel := context.WithTimeout(c.Request.Context(), 5*time.Second)
		defer cancel()

		report := getStatus(ctx)

		if ctx.Err() != nil {
			c.JSON(http.StatusServiceUnavailable, gin.H{
				"error": "cluster status collection timed out",
			})
			return
		}

		c.JSON(http.StatusOK, report)
	}
}
