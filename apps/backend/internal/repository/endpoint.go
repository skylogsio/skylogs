package repository

import (
	"github.com/skylogsio/skylogs/internal/dtos"
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/util_models"
)

type EndpointRepository interface {
	CreateEndpoint(endpointInterface models.EndpointInterface) error
	UpdateEndpoint(endpoint *dtos.UpdateEndpoint) (models.EndpointInterface, error)
	GetEndpoints(pagination *util_models.Pagination) (*util_models.ResultIndex, error)
}
