package auth

import (
	"errors"
	"github.com/golang-jwt/jwt/v5"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/repository"
	"time"

	"github.com/redis/go-redis/v9"
)

type AuthService struct {
	redis *redis.Client
	repo  repository.AuthRepository
}

type AuthConfiguration func(*AuthService) error

func New(cfgs ...AuthConfiguration) (*AuthService, error) {
	us := &AuthService{}

	for _, cfg := range cfgs {
		err := cfg(us)
		if err != nil {
			return nil, err
		}
	}

	return us, nil
}

func WithRedis(r *redis.Client) AuthConfiguration {
	return func(us *AuthService) error {
		us.redis = r
		return nil
	}
}

func WithRepository(r repository.AuthRepository) AuthConfiguration {
	return func(us *AuthService) error {
		us.repo = r
		return nil
	}
}

func (u *AuthService) JwtSecret() []byte {
	return []byte(configs.Configs.Auth.SecretKey)
}

func (u *AuthService) generateJWT(username string, duration time.Duration) (string, error) {
	claims := jwt.MapClaims{
		"username": username,
		"exp":      time.Now().Add(duration).Unix(), // Token expires in 72 hours
	}

	token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)

	return token.SignedString(u.JwtSecret())
}

func (u *AuthService) ValidateJWT(tokenString string) (jwt.MapClaims, error) {

	token, err := jwt.Parse(tokenString, func(token *jwt.Token) (interface{}, error) {
		if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
			return nil, jwt.ErrSignatureInvalid
		}
		return u.JwtSecret(), nil
	})

	if err != nil || !token.Valid {
		return nil, errors.New("Invalid or expired token")
	}

	claims, ok := token.Claims.(jwt.MapClaims)
	if !ok {
		return nil, errors.New("Invalid token claims")
	}

	return claims, nil
}

func (u *AuthService) Login(m *models.User) (string, string, error) {

	err := u.repo.Login(m)
	if err != nil {
		return "", "", err
	}

	token, err := u.generateJWT(m.Username, time.Hour)

	if err != nil {
		return "", "", err
	}

	refreshToken, err := u.generateJWT(m.Username, 24*time.Hour)
	if err != nil {
		return "", "", err
	}

	return token, refreshToken, nil
}

func (u *AuthService) Refresh(username string) (string, string, error) {

	token, err := u.generateJWT(username, time.Hour)

	if err != nil {
		return "", "", err
	}

	refreshToken, err := u.generateJWT(username, 24*time.Hour)
	if err != nil {
		return "", "", err
	}

	return token, refreshToken, nil
}
