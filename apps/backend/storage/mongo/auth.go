package mongo

import (
	"errors"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/internal/models"
	"go.mongodb.org/mongo-driver/bson"
	"go.mongodb.org/mongo-driver/mongo"
	"golang.org/x/crypto/bcrypt"
)

func (m *MongoDB) Login(user *models.User) error {
	collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("users")

	filter := bson.M{"username": user.Username}
	var storedUser models.User
	err := collection.FindOne(nil, filter).Decode(&storedUser)
	if errors.Is(err, mongo.ErrNoDocuments) {
		return errors.New("invalid username or password")
	} else if err != nil {
		return errors.New("database error")
	}

	// Compare the provided password with the stored hashed password
	err = bcrypt.CompareHashAndPassword([]byte(storedUser.Password), []byte(user.Password))
	if err != nil {
		return errors.New("invalid username or password")
	}

	return nil
}
