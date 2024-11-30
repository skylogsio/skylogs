package http

import (
	"fmt"
	"github.com/gin-gonic/gin"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/internal/services/auth"
	"github.com/skylogsio/skylogs/internal/services/endpoint"
	"github.com/skylogsio/skylogs/internal/services/user"
)

type Services struct {
	engine          *gin.Engine
	UserService     *user.UserService
	AuthService     *auth.AuthService
	EndpointService *endpoint.EndpointService
}

func New(
	userService *user.UserService,
	authService *auth.AuthService,
	endpointService *endpoint.EndpointService,

) *Services {
	r := gin.New()

	return &Services{
		engine:          r,
		UserService:     userService,
		AuthService:     authService,
		EndpointService: endpointService,
	}

}

func (s *Services) Launch() error {

	s.engine.POST("/api/v1/user", s.CreateUser)
	s.engine.GET("/api/v1/users", s.validateJWT(), s.GetUsers)

	s.engine.POST("/api/v1/endpoint", s.validateJWT(), s.CreateEndpoint)
	s.engine.GET("/api/v1/endpoint", s.validateJWT(), s.GetEndpoints)

	s.engine.POST("/api/v1/auth/login", s.Login)
	s.engine.GET("/api/v1/auth/refresh", s.validateJWT(), s.RefreshToken)

	addr := fmt.Sprintf(":%d", configs.Configs.HTTP.Port)

	err := s.engine.Run(addr)

	if err != nil {
		return err
	}

	return nil
}
