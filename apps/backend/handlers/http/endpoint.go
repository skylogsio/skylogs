package http

import (
	"github.com/skylogsio/skylogs/internal/models"
	"net/http"

	"github.com/gin-gonic/gin"
)

func (s *Services) CreateEndpoint(c *gin.Context) {
	var endpointModel models.Endpoint

	// Bind JSON payload to the endpoint struct
	if err := c.ShouldBindJSON(&endpointModel); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid request payload"})
		return
	}

	endpointModel.UserId = c.GetString("id")

	err := s.EndpointService.CreateEndpoint(&endpointModel)

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to create endpoint", "message": err.Error()})
		return
	}

	c.JSON(http.StatusOK, gin.H{"message": "endpoint created successfully"})

}

func (s *Services) GetEndpoints(c *gin.Context) {

	endpoints, err := s.EndpointService.GetEndpoints()

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to get endpoints", "message": err.Error()})
		return
	}

	c.JSON(http.StatusOK, gin.H{"data": endpoints})

}
