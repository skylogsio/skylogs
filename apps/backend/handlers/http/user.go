package http

import (
	"github.com/skylogsio/skylogs/internal/dtos"
	"github.com/skylogsio/skylogs/internal/util_models"
	"net/http"

	"github.com/gin-gonic/gin"
)

func (s *Services) CreateUser(c *gin.Context) {
	var userModel dtos.CreateUser

	if err := c.ShouldBindJSON(&userModel); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid request payload"})
		return
	}

	err := s.UserService.CreateUser(&userModel)

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to create user", "message": err.Error()})
		return
	}

	c.JSON(http.StatusOK, gin.H{"message": "User created successfully"})

}

func (s *Services) GetUsers(c *gin.Context) {

	pc, _ := c.Get("pageConfigs")
	PagConfigs := pc.(util_models.Pagination)

	users, err := s.UserService.GetUsers(&PagConfigs)

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to create user", "message": err.Error()})
		return
	}

	c.JSON(http.StatusOK, gin.H{"data": users})

}
