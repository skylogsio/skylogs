package http

import (
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/util_models"
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

	pc, _ := c.Get("pageConfigs")
	PagConfigs := pc.(util_models.Pagination)

	result, err := s.EndpointService.GetEndpoints(&PagConfigs)

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to get endpoints", "message": err.Error()})
		return
	}

	c.JSON(http.StatusOK, result)

}
