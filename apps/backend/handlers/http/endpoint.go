package http

import (
	"github.com/skylogsio/skylogs/internal/dtos"
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/util_models"
	"net/http"
	"time"

	"github.com/gin-gonic/gin"
)

func (s *Services) CreateEndpoint(c *gin.Context) {
	var endpointModel models.Endpoint

	// Bind JSON payload to the endpoint struct
	if err := c.ShouldBindBodyWithJSON(&endpointModel); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid request payload"})
		return
	}

	if endpointModel.GetType() == "telegram" {

		var endpointModelTelegram models.EndpointTelegram
		if err := c.ShouldBindBodyWithJSON(&endpointModelTelegram); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid request payload"})
			return
		}

		endpointModelTelegram.UserId = c.GetString("id")
		endpointModelTelegram.CreatedAt = uint64(time.Now().Unix())

		var ei models.EndpointInterface = &endpointModelTelegram
		err := s.EndpointService.CreateEndpoint(ei)

		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to create endpoint", "message": err.Error()})
			return
		}

	} else {

		endpointModel.UserId = c.GetString("id")
		endpointModel.CreatedAt = uint64(time.Now().Unix())

		var ei models.EndpointInterface = &endpointModel
		err := s.EndpointService.CreateEndpoint(ei)

		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to create endpoint", "message": err.Error()})
			return
		}

	}

	c.JSON(http.StatusOK, gin.H{"message": "endpoint created successfully"})

}
func (s *Services) UpdateEndpoint(c *gin.Context) {
	var endpointModel dtos.UpdateEndpoint

	// Bind JSON payload to the endpoint struct
	if err := c.ShouldBindJSON(&endpointModel); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid request payload"})
		return
	}

	endpointModel.Id = c.Param("id")
	endpointModel.UserId = c.GetString("id")

	err := s.EndpointService.UpdateEndpoint(&endpointModel)

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
