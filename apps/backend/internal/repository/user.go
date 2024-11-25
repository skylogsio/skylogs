package repository

import (
	"github.com/skylogsio/skylogs/internal/models"
)

type UserRepository interface {
	CreateUser(*models.User) error
	GetUsers() (*[]models.User, error)
}
