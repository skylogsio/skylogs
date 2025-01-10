package repository

import "github.com/skylogsio/skylogs/internal/models"

type AuthRepository interface {
	Login(*models.Auth) (*models.User, error)
}
