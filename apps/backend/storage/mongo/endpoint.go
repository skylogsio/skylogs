package mongo

import (
	"errors"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/internal/dtos"
	"github.com/skylogsio/skylogs/internal/models"
	"github.com/skylogsio/skylogs/internal/util_models"
	"go.mongodb.org/mongo-driver/bson"
	"go.mongodb.org/mongo-driver/bson/primitive"
	"go.mongodb.org/mongo-driver/mongo/options"
)

func (m *MongoDB) CreateEndpoint(endpoint models.EndpointInterface) error {
	collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("endpoints")

	_, err := collection.InsertOne(nil, endpoint)
	if err != nil {
		return err
	}
	return nil
}

func (m *MongoDB) UpdateEndpoint(dtoEndpoint *dtos.UpdateEndpoint) (models.EndpointInterface, error) {

	//existEndpoint, err := m.FindByID(dtoEndpoint.Id)
	//
	//if err != nil {
	//	return nil, err
	//}
	//
	//endpoint , _ := existEndpoint.(*models.Endpoint)
	//
	//if endpoint.UserId != dtoEndpoint.UserId {
	return nil, errors.New("user not allowed")
	//}
	//
	//collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("endpoints")
	//
	//objectID, err := primitive.ObjectIDFromHex(dtoEndpoint.Id)
	//if err != nil {
	//	return nil, err
	//}
	//switch endpoint.Type {
	//
	//}

	//update := bson.M{"$set":}
	//result, err := collection.UpdateOne(nil, bson.M{"_id": objectID}, update)
	//if err != nil {
	//	return nil, err
	//}
	//
	//if result.MatchedCount == 0 {
	//	return nil, errors.New("endpoint not found")
	//}

	//return
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

func (m *MongoDB) FindByID(id string) (models.EndpointInterface, error) {

	collection := m.db.Database(configs.Configs.Mongo.DBName).Collection("endpoints")

	objectID, err := primitive.ObjectIDFromHex(id)
	if err != nil {
		return nil, err
	}

	var model bson.M
	err = collection.FindOne(nil, bson.M{"_id": objectID}).Decode(&model)
	if err != nil {
		return nil, err
		//if err == mongo.ErrNoDocuments {
		//	c.JSON(404, gin.H{"error": "Document not found"})
		//} else {
		//	c.JSON(500, gin.H{"error": "Failed to fetch document"})
		//}
	}
	var result models.EndpointInterface = nil
	switch model["type"] {
	case "telegram":
		var endpointTelegram models.EndpointTelegram
		bsonBytes, _ := bson.Marshal(model)
		errM := bson.Unmarshal(bsonBytes, &endpointTelegram)
		if errM != nil {
			return nil, errM
		}
		result = &endpointTelegram
	default:
		var endpoint models.Endpoint
		bsonBytes, _ := bson.Marshal(model)
		errM := bson.Unmarshal(bsonBytes, &endpoint)
		if errM != nil {
			return nil, errM
		}
		result = &endpoint
	}

	return result, nil
}
