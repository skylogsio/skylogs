package main

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"

	"github.com/hashicorp/raft"
	"github.com/rs/zerolog"
	"github.com/rs/zerolog/log"
)

type HTTPServer struct {
	node   *Node
	config *Config
	logger zerolog.Logger
}

func NewHTTPServer(node *Node, config *Config) *HTTPServer {
	log.Logger = log.Output(zerolog.ConsoleWriter{Out: log.Logger})

	return &HTTPServer{
		node:   node,
		config: config,
		logger: log.With().Str("component", "http").Logger(),
	}
}

func (h *HTTPServer) Start() error {
	mux := http.NewServeMux()
	mux.HandleFunc("/health", h.healthHandler)
	mux.HandleFunc("/set", h.handleSet)
	mux.HandleFunc("/get", h.handleGet)
	mux.HandleFunc("/join", h.handleJoin)
	mux.HandleFunc("/status", h.handleStatus)
	mux.HandleFunc("/leader", h.handleLeader)
	addr := fmt.Sprintf("%s:%d", h.config.BindAddress, h.config.HTTPPort)
	fmt.Println("\n\nhttp join address : ", addr, "\n\n")

	h.logger.Info().Str("address", addr).Msg("starting HTTP server")

	return http.ListenAndServe(addr, mux)
}

type JoinRequest struct {
	NodeID      string `json:"node_id"`
	RaftAddress string `json:"raft_address"`
}

func (h *HTTPServer) handleSet(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	body, err := io.ReadAll(r.Body)
	if err != nil {
		http.Error(w, err.Error(), http.StatusBadRequest)
		return
	}
	defer r.Body.Close()

	var req struct {
		Key   string          `json:"key"`
		Value json.RawMessage `json:"value"`
	}
	if err := json.Unmarshal(body, &req); err != nil {
		http.Error(w, err.Error(), http.StatusBadRequest)
		return
	}

	if req.Key == "" {
		http.Error(w, "key is required", http.StatusBadRequest)
		return
	}

	// value: null (or omitted) is a tombstone delete; otherwise store raw JSON text.
	cmd := Command{Key: req.Key}
	if len(req.Value) == 0 || string(req.Value) == "null" {
		cmd.Op = "delete"
	} else {
		cmd.Op = "set"
		cmd.Value = string(req.Value)
	}

	cmdBytes, err := json.Marshal(cmd)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	if err := h.node.Apply(cmdBytes); err != nil {
		h.logger.Error().Err(err).Msg("failed to apply command")
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	if cmd.Op == "delete" {
		h.logger.Info().Str("key", req.Key).Msg("key deleted")
	} else {
		h.logger.Info().Str("key", req.Key).Str("value", string(req.Value)).Msg("key set")
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}

func (h *HTTPServer) handleGet(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	key := r.URL.Query().Get("key")

	if key == "" {
		// Return all data
		data := h.node.fsm.GetAll()
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(data)
		return
	}

	value, ok := h.node.fsm.Get(key)
	if !ok {
		http.Error(w, "key not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"key": key, "value": value})
}

func (h *HTTPServer) handleJoin(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	body, err := io.ReadAll(r.Body)
	if err != nil {
		http.Error(w, err.Error(), http.StatusBadRequest)
		return
	}
	defer r.Body.Close()

	var req JoinRequest
	if err := json.Unmarshal(body, &req); err != nil {
		http.Error(w, err.Error(), http.StatusBadRequest)
		return
	}

	if err := h.node.Join(req.NodeID, req.RaftAddress); err != nil {
		h.logger.Error().Err(err).Msg("failed to join node")
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	h.logger.Info().Str("node_id", req.NodeID).Msg("node joined")

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}

func (h *HTTPServer) handleStatus(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	isLeader := h.node.IsLeader()
	leader := h.node.GetLeader()

	status := map[string]interface{}{
		"node_id":   h.config.NodeID,
		"is_leader": isLeader,
		"leader":    leader,
		"state":     h.node.raft.State().String(),
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(status)
}

func (h *HTTPServer) healthHandler(w http.ResponseWriter, r *http.Request) {
	state := h.node.raft.State()

	role := "follower"
	if state == raft.Leader {
		role = "leader"
	}

	response := map[string]interface{}{
		"role":    role,
		"state":   state.String(),
		"node_id": h.node.config.NodeID,
		"leader":  string(h.node.raft.Leader()),
	}

	// اگه leader نیست، status code 503 برمی‌گردونه
	if state != raft.Leader {
		w.WriteHeader(http.StatusServiceUnavailable)
	}

	json.NewEncoder(w).Encode(response)
}

func (h *HTTPServer) handleLeader(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	isLeader := h.node.IsLeader()
	leaderAddr := string(h.node.raft.Leader())

	w.Header().Set("Content-Type", "application/json")

	if isLeader {
		w.WriteHeader(http.StatusOK) // 200 → HAProxy این نود رو UP می‌بینه
	} else {
		w.WriteHeader(http.StatusServiceUnavailable) // 503 → HAProxy این نود رو DOWN می‌بینه
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"leader":  isLeader,
		"address": leaderAddr,
	})
}
