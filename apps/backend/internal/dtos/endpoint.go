package dtos

type UpdateEndpoint struct {
	Id       string
	UserId   string
	Name     string `json:"name" binding:"required"`
	Type     string `json:"type" binding:"required"`
	Value    string `json:"value" binding:"required"`
	ThreadID string `json:"thread_id"`
}
