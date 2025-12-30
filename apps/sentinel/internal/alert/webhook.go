package alert

import (
	"bytes"
	"encoding/json"
	"net/http"
	"time"
)

type Payload struct {
	Type     string    `json:"type"`
	Peer     string    `json:"peer"`
	Since    time.Time `json:"since"`
	Severity string    `json:"severity"`
}

func Send(webhook string, payload Payload) {
	data, _ := json.Marshal(payload)

	req, _ := http.NewRequest(
		http.MethodPost,
		webhook,
		bytes.NewBuffer(data),
	)

	req.Header.Set("Content-Type", "application/json")
	http.DefaultClient.Do(req)
}

