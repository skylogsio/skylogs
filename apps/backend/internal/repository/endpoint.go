package repository

import (
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/util_models"
)

type EndpointRepository interface {
	CreateEndpoint(*models.Endpoint) error
	GetEndpoints(pagination *util_models.Pagination) (*util_models.ResultIndex, error)
}
