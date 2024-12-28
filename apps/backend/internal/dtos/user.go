package dtos

type CreateUserInput struct {
	Username string   `json:"username" binding:"required"`
	Password string   `json:"password" binding:"required"` // Included for binding
	Roles    []string `json:"roles"`
}
