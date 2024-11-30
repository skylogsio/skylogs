package repository

import (
	"github.com/skylogsio/skylogs/internal/models"
)

type EndpointRepository interface {
	CreateEndpoint(*models.Endpoint) error
	GetEndpoints() (*[]models.Endpoint, error)
}
