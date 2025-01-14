package models

import (
	"go.mongodb.org/mongo-driver/bson/primitive"
)

type Endpoint struct {
	ID        primitive.ObjectID `bson:"_id,omitempty" json:"id"`
	UserId    string             `bson:"user_id" json:"user_id"`
	Name      string             `bson:"name" json:"name"`
	Type      string             `bson:"type" json:"type"`
	Value     string             `bson:"value" json:"value"`
	CreatedAt uint64             `bson:"created_at" json:"created_at"`
	//Metadata map[string]interface{} `json:"metadata" bson:"metadata"`
}

type EndpointTelegram struct {
	Endpoint `bson:",inline"`
	ThreadID string `bson:"thread_id" json:"thread_id"`
}

func (e *Endpoint) GetType() string {
	return e.Type
}

func (e *EndpointTelegram) GetType() string {
	return e.Type
}

type EndpointInterface interface {
	GetType() string
}
