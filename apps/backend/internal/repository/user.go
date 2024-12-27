package repository

import (
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/util_models"
)

type UserRepository interface {
	CreateUser(*models.User) error
	GetUserByUserName(string) (*models.User, error)
	GetUsers(pagination *util_models.Pagination) (*util_models.ResultIndex, error)
}
