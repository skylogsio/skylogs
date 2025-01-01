package dtos

type CreateUser struct {
	Username string   `json:"username" binding:"required"`
	Password string   `json:"password" binding:"required"`
	Roles    []string `json:"roles"`
}

type UpdateUser struct {
	UserId   string   `json:"userId" binding:"required"`
	Username string   `json:"username" binding:"required"`
	Roles    []string `json:"roles"`
}

type UpdateUserPassword struct {
	UserId     string `json:"userId" binding:"required"`
	Password   string `json:"password" binding:"required"`
	RePassword string `json:"rePassword" binding:"required"`
}
