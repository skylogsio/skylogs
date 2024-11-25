package repository

import "github.com/skylogsio/skylogs/internal/models"

type AuthRepository interface {
	Login(*models.User) error
}
