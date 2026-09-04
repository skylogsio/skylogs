package heartbeat

import (
	"sync"
	"time"
)

// Registry tracks heartbeat State per peer key (sentinel id or name).
type Registry struct {
	mu     sync.RWMutex
	peers  map[string]*State
	uptime time.Time
}

func NewRegistry() *Registry {
	return &Registry{
		peers:  make(map[string]*State),
		uptime: time.Now(),
	}
}

func (r *Registry) GetOrCreate(key string) *State {
	r.mu.Lock()
	defer r.mu.Unlock()

	if s, ok := r.peers[key]; ok {
		return s
	}
	s := NewState()
	r.peers[key] = s
	return s
}

func (r *Registry) Get(key string) *State {
	r.mu.RLock()
	defer r.mu.RUnlock()
	return r.peers[key]
}

// Keys returns all tracked peer keys.
func (r *Registry) Keys() []string {
	r.mu.RLock()
	defer r.mu.RUnlock()
	out := make([]string, 0, len(r.peers))
	for k := range r.peers {
		out = append(out, k)
	}
	return out
}

func (r *Registry) Uptime() time.Duration {
	return time.Since(r.uptime)
}
