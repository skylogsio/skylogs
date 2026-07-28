package model

import (
	"bytes"
	"encoding/json"
	"net/http"
	"time"

	"github.com/rs/zerolog"
)

// Notifier pushes each committed FSM change to a local app (sidecar pattern).
// Failures are logged only: Raft must not stall or roll back on notifier errors.
type Notifier struct {
	url    string
	secret string
	header string
	client *http.Client
	logger zerolog.Logger
}

func NewNotifier(url, secret, header string, logger zerolog.Logger) *Notifier {
	if url == "" {
		return nil
	}
	if header == "" {
		header = "X-Raft-Notify-Secret"
	}
	return &Notifier{
		url:    url,
		secret: secret,
		header: header,
		client: &http.Client{Timeout: 3 * time.Second},
		logger: logger.With().Str("component", "notifier").Logger(),
	}
}

// Notify sends {key, value} asynchronously. value nil means JSON null (delete).
func (n *Notifier) Notify(key string, value json.RawMessage) {
	if n == nil {
		return
	}
	go n.send(key, value)
}

func (n *Notifier) send(key string, value json.RawMessage) {
	payload := struct {
		Key   string          `json:"key"`
		Value json.RawMessage `json:"value"`
	}{
		Key:   key,
		Value: value,
	}
	if payload.Value == nil {
		payload.Value = json.RawMessage("null")
	}

	body, err := json.Marshal(payload)
	if err != nil {
		n.logger.Error().Err(err).Str("key", key).Msg("failed to marshal notifier payload")
		return
	}

	req, err := http.NewRequest(http.MethodPost, n.url, bytes.NewReader(body))
	if err != nil {
		n.logger.Error().Err(err).Str("key", key).Msg("failed to build notifier request")
		return
	}
	req.Header.Set("Content-Type", "application/json")
	if n.secret != "" {
		req.Header.Set(n.header, n.secret)
	}

	resp, err := n.client.Do(req)
	if err != nil {
		n.logger.Error().Err(err).Str("key", key).Str("url", n.url).Msg("notifier failed")
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		n.logger.Error().
			Int("status", resp.StatusCode).
			Str("key", key).
			Str("url", n.url).
			Msg("notifier rejected")
		return
	}

	n.logger.Debug().Str("key", key).Msg("notifier ok")
}
