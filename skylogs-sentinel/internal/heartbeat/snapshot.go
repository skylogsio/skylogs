package heartbeat

import "time"

type Snapshot struct {
	Status        string
	LastSeen     time.Time
	FailureCount int
	Uptime       time.Duration
}

