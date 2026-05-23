package discovery

import "time"

// ClusterPeersPath is the HTTP path agents use on the main sentinel (signed GET).
const ClusterPeersPath = "/cluster/peers"

// ClusterPeersResponse is JSON returned by GET ClusterPeersPath and stored in disk cache envelopes when mirroring fetch responses.
type ClusterPeersResponse struct {
	Peers     []Peer    `json:"peers"`
	UpdatedAt time.Time `json:"updated_at"`
}
