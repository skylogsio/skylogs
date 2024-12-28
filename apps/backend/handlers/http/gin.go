package http

import (
	"fmt"
	"github.com/gin-gonic/gin"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/internal/services/auth"
	"github.com/skylogsio/skylogs/internal/services/endpoint"
	"github.com/skylogsio/skylogs/internal/services/user"
	"github.com/skylogsio/skylogs/internal/util_models"
	"github.com/webstradev/gin-pagination/v2/pkg/pagination"
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

	paginator := pagination.New(
		pagination.WithPageText("page"),
		pagination.WithSizeText("perPage"),
		pagination.WithDefaultPage(1),
		pagination.WithDefaultPageSize(25),
		pagination.WithMinPageSize(5),
		pagination.WithMaxPageSize(100),
	)

	s.engine.POST("/api/v1/user", s.CreateUser)
	s.engine.GET("/api/v1/users", s.validateJWT(), paginator, PaginationMiddleware(), s.GetUsers)

	s.engine.POST("/api/v1/endpoint", s.validateJWT(), s.CreateEndpoint)
	s.engine.GET("/api/v1/endpoint", s.validateJWT(), paginator, PaginationMiddleware(), s.GetEndpoints)

	s.engine.POST("/api/v1/auth/login", s.Login)
	s.engine.GET("/api/v1/auth/refresh", s.validateJWT(), s.RefreshToken)

	addr := fmt.Sprintf(":%d", configs.Configs.HTTP.Port)

	err := s.engine.Run(addr)

	if err != nil {
		return err
	}

	return nil
}

func PaginationMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {

		sortTypeQ := c.Query("sortType")
		sortType := util_models.SortAscending
		if sortTypeQ == string(util_models.SortDescending) {
			sortType = util_models.SortDescending
		}

		sortByQ := c.Query("sortBy")
		sortBy := "_id"
		if sortByQ != "" {
			sortBy = sortByQ
		}

		pageConfigs := util_models.Pagination{
			Page:     int64(c.GetInt("page")),
			PageSize: int64(c.GetInt("perPage")),
			SortBy:   sortBy,
			SortType: sortType,
		}

		c.Set("pageConfigs", pageConfigs)

		c.Next()
	}
}
