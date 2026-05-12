package discovery

import (
	"sync"
)

// PeerList holds the latest filtered peer snapshot and broadcasts updates.
type PeerList struct {
	mu     sync.RWMutex
	peers  []Peer
	closed bool
	// notify carries full snapshots (capacity 1 coalesces bursts).
	notify chan []Peer
}

func NewPeerList() *PeerList {
	return &PeerList{
		notify: make(chan []Peer, 1),
	}
}

// Close closes the updates channel so subscribers can exit. Safe to call once.
func (p *PeerList) Close() {
	p.mu.Lock()
	defer p.mu.Unlock()
	if p.closed {
		return
	}
	p.closed = true
	if p.notify != nil {
		close(p.notify)
	}
}

// Snapshot returns the last Update'd peers (read-only slice — do not mutate).
func (p *PeerList) Snapshot() []Peer {
	p.mu.RLock()
	defer p.mu.RUnlock()
	out := make([]Peer, len(p.peers))
	copy(out, p.peers)
	return out
}

// Update stores peers and notifies subscribers.
func (p *PeerList) Update(peers []Peer) {
	p.mu.Lock()
	if p.closed {
		p.mu.Unlock()
		return
	}
	p.peers = peers
	ch := p.notify
	p.mu.Unlock()

	if ch == nil {
		return
	}
	select {
	case ch <- peers:
	default:
		select {
		case <-ch:
		default:
		}
		select {
		case ch <- peers:
		default:
		}
	}
}

// Updates returns the broadcast channel (same slice reference as Snapshot after receive).
func (p *PeerList) Updates() <-chan []Peer {
	return p.notify
}

// FilterPeers removes self (by instance name or sentinel id) and peers without a heartbeat URL.
func FilterPeers(all []Peer, selfInstanceName, selfSentinelID string) []Peer {
	var out []Peer
	for _, peer := range all {
		if selfInstanceName != "" && peer.Name == selfInstanceName {
			continue
		}
		if selfSentinelID != "" && peer.SentinelID == selfSentinelID {
			continue
		}
		if peer.HeartbeatURL == "" {
			continue
		}
		out = append(out, peer)
	}
	return out
}
