package heartbeat

import (
	"sync"
	"time"
)

type State struct {
	mu           sync.RWMutex
	lastSeen     time.Time
	currentAlive bool
}

func NewState() *State {
	return &State{}
}

func (s *State) Update(t time.Time) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.lastSeen = t
	s.currentAlive = true
}

func (s *State) IsAlive(timeout time.Duration) bool {
	s.mu.RLock()
	defer s.mu.RUnlock()

	if s.lastSeen.IsZero() {
		return false
	}
	return time.Since(s.lastSeen) <= timeout
}

