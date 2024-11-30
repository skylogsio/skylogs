package mongo

import (
	"errors"
	"fmt"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/internal/models"
	"go.mongodb.org/mongo-driver/bson"
)

func (m *MongoDB) CreateEndpoint(endpoint *models.Endpoint) error {
	collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("endpoints")

	_, err := collection.InsertOne(nil, endpoint)
	if err != nil {
		return err
	}
	fmt.Println("Inserted endpoint: ", endpoint)
	return nil
}

func (m *MongoDB) GetEndpoints() (*[]models.Endpoint, error) {
	collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("endpoints")

	cursor, err := collection.Find(nil, bson.M{})
	if err != nil {
		return nil, errors.New("failed to fetch endpoints")
	}
	defer cursor.Close(nil)

	var endpoints []models.Endpoint
	if err2 := cursor.All(nil, &endpoints); err2 != nil {
		return nil, errors.New("failed to decode endpoints")
	}

	return &endpoints, nil
}
