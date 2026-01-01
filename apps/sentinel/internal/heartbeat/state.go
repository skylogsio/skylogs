package heartbeat

import (
	"sync"
	"time"
)

type State struct {
	mu           sync.Mutex
	FailureCount int
	LastSeen     time.Time
	Unhealthy    bool
	StartTime    time.Time
}

func NewState() *State {
	return &State{
		LastSeen:  time.Now(),
		StartTime: time.Now(),
	}
}

func (s *State) MarkSeen() {
	s.mu.Lock()
	defer s.mu.Unlock()

	s.LastSeen = time.Now()
	s.Unhealthy = false
}

func (s *State) MarkUnhealthy() {
	s.mu.Lock()
	defer s.mu.Unlock()

	s.Unhealthy = true
}

func (s *State) IsUnhealthy() bool {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.Unhealthy
}

func (s *State) TimeSinceLastSeen() time.Duration {
	s.mu.Lock()
	defer s.mu.Unlock()

	return time.Since(s.LastSeen)
}
func (s *State) Snapshot() Snapshot {
	s.mu.Lock()
	defer s.mu.Unlock()

	status := "healthy"
	if time.Since(s.LastSeen) > 10*time.Second {
		status = "unhealthy"
	}

	return Snapshot{
		Status:       status,
		LastSeen:     s.LastSeen,
		FailureCount: s.FailureCount,
		Uptime:       time.Since(s.StartTime),
	}
}
