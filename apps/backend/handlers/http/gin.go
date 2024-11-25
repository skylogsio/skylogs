package http

import (
	"fmt"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/services/auth"
	"github.com/skylogsio/skylogs/internal/services/user"
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
)

type Services struct {
	engine      *gin.Engine
	UserService *user.UserService
	AuthService *auth.AuthService
}

func New(
	userService *user.UserService,
	authService *auth.AuthService,

) *Services {
	r := gin.New()

	return &Services{
		engine:      r,
		UserService: userService,
		AuthService: authService,
	}

}

func (s *Services) Launch() error {

	s.engine.POST("/api/v1/user", s.createUser)
	s.engine.GET("/api/v1/users", s.validateJWT(), s.GetUsers)

	s.engine.POST("/api/v1/auth/login", s.Login)
	s.engine.GET("/api/v1/auth/refresh", s.validateJWT(), s.RefreshToken)

	addr := fmt.Sprintf(":%d", configs.Configs.HTTP.Port)

	err := s.engine.Run(addr)

	if err != nil {
		return err
	}

	return nil
}

func (s *Services) createUser(c *gin.Context) {
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

func (s *Services) Login(c *gin.Context) {
	var userModel models.User

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

	token, refreshToken, err := s.AuthService.Refresh(c.GetString("username"))

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
		c.Next()
	}
}
