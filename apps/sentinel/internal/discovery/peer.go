package discovery

// Peer is one remote Sentinel to watch (configured in config.yaml under peers:).
type Peer struct {
	ID            string `json:"id,omitempty" yaml:"id,omitempty"`
	Name          string `json:"name" yaml:"name"`
	Type          string `json:"type,omitempty" yaml:"type,omitempty"`
	URL           string `json:"url,omitempty" yaml:"url,omitempty"`
	SentinelURL   string `json:"sentinelUrl,omitempty" yaml:"sentinel_url,omitempty"`
	SentinelID    string `json:"sentinelId" yaml:"sentinel_id"`
	Priority      int    `json:"priority,omitempty" yaml:"priority,omitempty"`
	HeartbeatURL  string `json:"heartbeatUrl" yaml:"heartbeat_url"`
}

// Key returns the registry / heartbeat key for this peer. Prefer sentinelId (must match
// that zone's SENTINEL_ID / config sentinel.id) so it matches X-SkyLogs-Sentinel-Id.
func (p Peer) Key() string {
	if p.SentinelID != "" {
		return p.SentinelID
	}
	return p.Name
}
