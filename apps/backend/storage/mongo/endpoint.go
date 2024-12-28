package mongo

import (
	"errors"
	"fmt"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/util_models"
	"go.mongodb.org/mongo-driver/bson"
	"go.mongodb.org/mongo-driver/mongo/options"
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

func (m *MongoDB) GetEndpoints(pageConfigs *util_models.Pagination) (*util_models.ResultIndex, error) {
	collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("endpoints")

	totalCount, err := collection.CountDocuments(nil, bson.M{})
	if err != nil {
		return nil, errors.New("failed to count endpoints")
	}

	totalPages := (totalCount + pageConfigs.PageSize - 1) / pageConfigs.PageSize
	skip := (pageConfigs.Page - 1) * pageConfigs.PageSize
	limit := pageConfigs.PageSize

	sortOrder := 1
	if pageConfigs.SortType == util_models.SortDescending {
		sortOrder = -1
	}
	findOptions := options.Find()
	findOptions.SetSkip(skip)
	findOptions.SetLimit(limit)
	findOptions.SetSort(bson.D{{Key: pageConfigs.SortBy, Value: sortOrder}})

	cursor, err := collection.Find(nil, bson.M{}, findOptions)
	if err != nil {
		return nil, errors.New("failed to fetch endpoints")
	}
	defer cursor.Close(nil)

	var endpoints []models.Endpoint
	if err2 := cursor.All(nil, &endpoints); err2 != nil {
		return nil, errors.New("failed to decode endpoints")
	}

	result := &util_models.ResultIndex{
		CurrentPage: pageConfigs.Page,
		TotalPage:   totalPages,
		TotalData:   totalCount,
		Data:        &endpoints,
	}

	return result, nil
}
