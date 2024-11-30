package http

import (
	"github.com/skylogsio/skylogs/internal/models"
	"net/http"

	"github.com/gin-gonic/gin"
)

func (s *Services) CreateUser(c *gin.Context) {
	var userModel models.User

	// Bind JSON payload to the User struct
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

	users, err := s.UserService.GetUsers()

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to create user", "message": err.Error()})
		return
	}

	c.JSON(http.StatusOK, gin.H{"data": users})

}
