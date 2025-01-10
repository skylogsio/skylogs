package models

type Auth struct {
	Username string `bson:"username"`
	Password string `bson:"password"`
}
