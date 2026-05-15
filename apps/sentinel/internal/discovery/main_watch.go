package discovery

import "strings"

// AppendMainWatchPeer adds a synthetic peer for the cluster main sentinel so agents
// run the same outbound heartbeat watcher (and webhook) when main is down.
// Skips append when baseURL is empty or a peer already uses the same heartbeat URL / sentinel id.
func AppendMainWatchPeer(peers []Peer, baseURL, name, sentinelID string) []Peer {
	base := strings.TrimRight(strings.TrimSpace(baseURL), "/")
	if base == "" {
		return peers
	}
	if name == "" {
		name = "main"
	}
	main := Peer{
		Name:         name,
		Type:         "main",
		SentinelID:   sentinelID,
		HeartbeatURL: base + "/heartbeat",
	}
	for _, p := range peers {
		if p.HeartbeatURL == main.HeartbeatURL {
			return peers
		}
		if sentinelID != "" && p.SentinelID == sentinelID {
			return peers
		}
	}
	out := make([]Peer, len(peers), len(peers)+1)
	copy(out, peers)
	return append(out, main)
}
