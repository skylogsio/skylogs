package repository

import (
	"github.com/skylogsio/skylogs/internal/dtos"
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/util_models"
)

type UserRepository interface {
	CreateUser(input *dtos.CreateUserInput) error
	GetUserByUserName(string) (*models.User, error)
	GetUsers(pagination *util_models.Pagination) (*util_models.ResultIndex, error)
}
