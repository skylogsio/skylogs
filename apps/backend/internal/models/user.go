package models

import (
	"go.mongodb.org/mongo-driver/bson/primitive"
)

type User struct {
	ID       primitive.ObjectID `bson:"_id,omitempty" json:"id"`
	Username string             `bson:"username" json:"user name"`
	Password string             `bson:"password,omitempty" json:"-"`
	Roles    []string           `bson:"roles" json:"roles"`
}
