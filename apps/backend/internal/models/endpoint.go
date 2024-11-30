package models

import (
	"go.mongodb.org/mongo-driver/bson/primitive"
)

type Endpoint struct {
	ID       primitive.ObjectID `bson:"_id,omitempty" json:"id"`
	UserId   string             `bson:"user_id" json:"user_id"`
	Name     string             `bson:"name" json:"name"`
	Type     string             `bson:"type" json:"type"`
	Value    string             `bson:"value" json:"value"`
	MetaData []string           `bson:"metadata" json:"metadata"` // Added roles field
}
