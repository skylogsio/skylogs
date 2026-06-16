package heartbeat

import (
	"context"
	"sync"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
)

type runInfo struct {
	cancel context.CancelFunc
	url    string
}

// Supervisor tracks one outbound watcher per peer key.
type Supervisor struct {
	mu     sync.Mutex
	active map[string]runInfo
	reg    *Registry
	wc     WatcherConfig
	alerts AlertHandler
}

// NewSupervisor constructs a supervisor.
func NewSupervisor(
	reg *Registry,
	wc WatcherConfig,
	alerts AlertHandler,
) *Supervisor {
	return &Supervisor{
		active: make(map[string]runInfo),
		reg:    reg,
		wc:     wc,
		alerts: alerts,
	}
}

// Reconcile matches running watchers to the desired peer set (add / remove / restart on URL change).
func (s *Supervisor) Reconcile(ctx context.Context, peers []discovery.Peer) {
	want := make(map[string]discovery.Peer)
	for _, p := range peers {
		if p.HeartbeatURL == "" {
			continue
		}
		want[p.Key()] = p
	}

	s.mu.Lock()
	for k, ri := range s.active {
		p, ok := want[k]
		if !ok || ri.url != p.HeartbeatURL {
			ri.cancel()
			delete(s.active, k)
		}
	}
	s.mu.Unlock()

	for _, p := range want {
		s.mu.Lock()
		if ri, ok := s.active[p.Key()]; ok && ri.url == p.HeartbeatURL {
			s.mu.Unlock()
			continue
		}
		if old, ok := s.active[p.Key()]; ok {
			old.cancel()
			delete(s.active, p.Key())
		}
		cctx, cancel := context.WithCancel(ctx)
		s.active[p.Key()] = runInfo{cancel: cancel, url: p.HeartbeatURL}
		s.mu.Unlock()

		p := p
		go RunPeerWatcher(cctx, p, s.reg, s.wc, s.alerts)
	}
}

// Shutdown cancels all active watchers.
func (s *Supervisor) Shutdown() {
	s.mu.Lock()
	defer s.mu.Unlock()
	for _, ri := range s.active {
		ri.cancel()
	}
	s.active = make(map[string]runInfo)
}
