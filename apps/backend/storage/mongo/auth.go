package mongo

import (
	"errors"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/internal/models"
	"go.mongodb.org/mongo-driver/bson"
	"go.mongodb.org/mongo-driver/mongo"
	"golang.org/x/crypto/bcrypt"
)

func (m *MongoDB) Login(auth *models.Auth) (*models.User, error) {
	collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("users")

	filter := bson.M{"username": auth.Username}
	var storedUser models.User
	err := collection.FindOne(nil, filter).Decode(&storedUser)
	if errors.Is(err, mongo.ErrNoDocuments) {
		return nil, errors.New("invalid username or password")
	} else if err != nil {
		return nil, errors.New("database error")
	}

	err = bcrypt.CompareHashAndPassword([]byte(storedUser.Password), []byte(auth.Password))
	if err != nil {
		return nil, errors.New("invalid username or password")
	}

	return &storedUser, nil
}
