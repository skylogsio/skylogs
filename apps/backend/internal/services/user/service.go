package user

import (
	"github.com/redis/go-redis/v9"
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/repository"
	"github.com/skylogsio/skylogs/internal/util_models"
	"golang.org/x/crypto/bcrypt"
)

type UserService struct {
	redis *redis.Client
	repo  repository.UserRepository
}

type UserConfiguration func(*UserService) error

func New(cfgs ...UserConfiguration) (*UserService, error) {
	us := &UserService{}

	for _, cfg := range cfgs {
		err := cfg(us)
		if err != nil {
			return nil, err
		}
	}

	return us, nil
}

func WithRedis(r *redis.Client) UserConfiguration {
	return func(us *UserService) error {
		us.redis = r
		return nil
	}
}

func WithRepository(r repository.UserRepository) UserConfiguration {
	return func(us *UserService) error {
		us.repo = r
		return nil
	}
}

func hashPassword(password string) (string, error) {
	bytes, err := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
	return string(bytes), err
}

func (u *UserService) CreateUser(m *models.User) error {

	hashedPassword, err := hashPassword(m.Password)
	if err != nil {
		return err
	}

	m.Password = hashedPassword

	err = u.repo.CreateUser(m)
	if err != nil {
		return err
	}
	return nil
}

func (u *UserService) GetUsers(pageConfigs *util_models.Pagination) (*util_models.ResultIndex, error) {

	result, err := u.repo.GetUsers(pageConfigs)
	if err != nil {
		return nil, err
	}

	return result, nil
}

func (u *UserService) GetUserByUserName(username string) (*models.User, error) {

	user, err := u.repo.GetUserByUserName(username)
	if err != nil {
		return nil, err
	}

	return user, nil
}
