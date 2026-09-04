package handler

import (
	"RAFT_Service/internal/model"
	"RAFT_Service/internal/service"
	"encoding/json"
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/rs/zerolog"
	"github.com/rs/zerolog/log"
)

type Handler struct {
	service *service.NodeService
	logger  zerolog.Logger
}

var setReq struct {
	Key   string          `json:"key"`
	Value json.RawMessage `json:"value"`
}
var delReq struct {
	Key string `json:"key"`
}

func NewHandler(service *service.NodeService) *Handler {

	return &Handler{
		service: service,
		logger: log.With().
			Str("component", "http").
			Logger(),
	}
}

func (h *Handler) HandleSet(c *gin.Context) {
	if err := c.ShouldBindJSON(&setReq); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{
			"error": err.Error(),
		})
		return
	}

	cmd := model.Command{
		Op:    "set",
		Key:   setReq.Key,
		Value: string(setReq.Value),
	}

	if err := h.service.Set(cmd); err != nil {
		h.logger.Error().
			Err(err).
			Msg("failed to apply command")

		c.JSON(http.StatusInternalServerError, gin.H{
			"error": err.Error(),
		})
		return
	}

	h.logger.Info().
		Str("key", setReq.Key).
		Str("value", string(setReq.Value)).
		Msg("key set")

	c.JSON(http.StatusOK, gin.H{
		"status": "ok",
	})
}

func (h *Handler) HandleGet(c *gin.Context) {
	key := c.Query("key")

	if key == "" {
		data := h.service.GetAll()

		c.JSON(http.StatusOK, data)
		return
	}

	value, ok := h.service.Get(key)
	if !ok {
		c.JSON(http.StatusNotFound, gin.H{
			"error": "key not found",
		})
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"key":   key,
		"value": value,
	})
}

func (h *Handler) HandleJoin(c *gin.Context) {
	var req service.JoinRequest

	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{
			"error": err.Error(),
		})
		return
	}

	if err := h.service.Join(req); err != nil {
		h.logger.Error().
			Err(err).
			Msg("failed to join node")

		c.JSON(http.StatusInternalServerError, gin.H{
			"error": err.Error(),
		})
		return
	}

	h.logger.Info().
		Str("node_id", req.NodeID).
		Msg("node joined")

	c.JSON(http.StatusOK, gin.H{
		"status": "ok",
	})
}

func (h *Handler) HandleStatus(c *gin.Context) {
	c.JSON(http.StatusOK, h.service.Status())
}

func (h *Handler) HandleLeader(c *gin.Context) {
	response, statusCode := h.service.Leader()
	c.JSON(statusCode, response)
}

func (h *Handler) HandleDelete(c *gin.Context) {
	if err := c.ShouldBindJSON(&delReq); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{
			"error": err.Error(),
		})
		return
	}

	cmd := model.Command{
		Op:  "delete",
		Key: delReq.Key,
	}

	if err := h.service.Delete(cmd); err != nil {
		h.logger.Error().
			Err(err).
			Msg("failed to apply command")

		c.JSON(http.StatusInternalServerError, gin.H{
			"error": err.Error(),
		})
		return
	}

	h.logger.Info().
		Str("key", delReq.Key).
		Msg("key removed")

	c.JSON(http.StatusOK, gin.H{
		"status": "ok",
	})
}

//func (h *Handler) HandleHealth(context *gin.Context) {
//
//}
