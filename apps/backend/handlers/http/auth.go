package http

import (
	"github.com/gin-gonic/gin"
	"github.com/skylogsio/skylogs/internal/models"
	"net/http"
	"strings"
)

func (s *Services) Login(c *gin.Context) {
	var userModel models.Auth

	// Bind JSON payload to the User struct
	if err := c.ShouldBindJSON(&userModel); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid request payload"})
		return
	}
	token, refreshToken, err := s.AuthService.Login(&userModel)

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to Login", "message": err.Error()})
		return
	}

	c.JSON(http.StatusOK, gin.H{"access_token": token, "refresh_token": refreshToken})

}

func (s *Services) RefreshToken(c *gin.Context) {

	user, err := s.UserService.GetUserByUserName(c.GetString("username"))

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to Refresh", "message": err.Error()})
		return
	}

	token, refreshToken, err := s.AuthService.Refresh(user)

	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to Refresh", "message": err.Error()})
		return
	}

	c.JSON(http.StatusOK, gin.H{"access_token": token, "refresh_token": refreshToken})

}

func (s *Services) validateJWT() gin.HandlerFunc {

	return func(c *gin.Context) {

		authHeader := c.GetHeader("Authorization")
		if authHeader == "" {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "Authorization header missing"})
			c.Abort()
			return
		}

		tokenString := strings.TrimPrefix(authHeader, "Bearer ")
		if tokenString == authHeader {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "Invalid token format"})
			c.Abort()
			return
		}

		claims, err := s.AuthService.ValidateJWT(tokenString)

		if err != nil {
			c.JSON(http.StatusUnauthorized, gin.H{"error": err.Error()})
			c.Abort()
		}

		c.Set("username", claims["username"])
		c.Set("id", claims["id"])

		c.Next()
	}
}
