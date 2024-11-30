package mongo

import (
	"context"
	"errors"
	"fmt"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/internal/models"
	"go.mongodb.org/mongo-driver/bson"
	"time"

	"go.mongodb.org/mongo-driver/mongo"
	"go.mongodb.org/mongo-driver/mongo/options"
)

type MongoDB struct {
	db *mongo.Client
}

func CreateClient() (*MongoDB, error) {

	cfg := configs.Configs.Mongo

	var uri string
	if cfg.Username != "" {
		uri = fmt.Sprintf("mongodb://%s:%s@%s:%d/%s?authSource=%s",
			cfg.Username, cfg.Password, cfg.Host, cfg.Port, cfg.DBName, cfg.AuthSource)
	} else {
		uri = fmt.Sprintf("mongodb://%s:%d/%s?authSource=%s",
			cfg.Host, cfg.Port, cfg.DBName, cfg.AuthSource)
	}

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	clientOpts := options.Client().ApplyURI(uri)

	client, err := mongo.Connect(ctx, clientOpts)
	if err != nil {
		return nil, err
	}

	err = client.Ping(ctx, nil)
	if err != nil {
		return nil, err
	}

	return &MongoDB{
		db: client,
	}, nil

}

func (m *MongoDB) CreateUser(user *models.User) error {
	collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("users")

	filter := bson.M{"username": user.Username}
	var existingUser models.User
	err := collection.FindOne(nil, filter).Decode(&existingUser)
	if err == nil {
		return errors.New("username already exists")
	}

	_, err = collection.InsertOne(nil, user)
	if err != nil {
		return err
	}
	fmt.Println("Inserted user: ", user)
	return nil
}

func (m *MongoDB) GetUsers() (*[]models.User, error) {
	collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("users")

	cursor, err := collection.Find(nil, bson.M{})
	if err != nil {
		return nil, errors.New("failed to fetch users")
	}
	defer cursor.Close(nil)

	var users []models.User
	if err2 := cursor.All(nil, &users); err2 != nil {
		return nil, errors.New("failed to decode users")
	}

	return &users, nil
}

func (m *MongoDB) GetUserByUserName(username string) (*models.User, error) {
	collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("users")

	filter := bson.M{"username": username}
	var user models.User
	err := collection.FindOne(nil, filter).Decode(&user)
	if err != nil {
		return nil, errors.New("username not exist")
	}

	return &user, nil
}
