package model

import "time"

type Heartbeat struct {
	DC        string    `json:"dc"`
	Node      string    `json:"node"`
	Timestamp time.Time `json:"timestamp"`
}

