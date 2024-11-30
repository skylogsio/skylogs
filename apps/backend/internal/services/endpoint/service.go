package endpoint

import (
	"github.com/redis/go-redis/v9"
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/repository"
)

type EndpointService struct {
	redis *redis.Client
	repo  repository.EndpointRepository
}

type EndpointConfiguration func(*EndpointService) error

func New(cfgs ...EndpointConfiguration) (*EndpointService, error) {
	us := &EndpointService{}

	for _, cfg := range cfgs {
		err := cfg(us)
		if err != nil {
			return nil, err
		}
	}

	return us, nil
}

func WithRedis(r *redis.Client) EndpointConfiguration {
	return func(us *EndpointService) error {
		us.redis = r
		return nil
	}
}

func WithRepository(r repository.EndpointRepository) EndpointConfiguration {
	return func(us *EndpointService) error {
		us.repo = r
		return nil
	}
}

func (u *EndpointService) CreateEndpoint(m *models.Endpoint) error {

	err := u.repo.CreateEndpoint(m)
	if err != nil {
		return err
	}
	return nil
}

func (u *EndpointService) GetEndpoints() (*[]models.Endpoint, error) {

	users, err := u.repo.GetEndpoints()
	if err != nil {
		return nil, err
	}

	return users, nil
}
