package heartbeat

import (
	"encoding/json"
	"net/http"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
)

type peerStatusRow struct {
	Key          string    `json:"key"`
	Name         string    `json:"name"`
	SentinelID   string    `json:"sentinel_id,omitempty"`
	HeartbeatURL string    `json:"heartbeat_url,omitempty"`
	Status       string    `json:"status"`
	LastSeen     time.Time `json:"last_seen"`
	UptimeSec    int       `json:"uptime_seconds"`
}

// StatusHandlerJSON returns aggregate status for this sentinel and all known peers.
func StatusHandlerJSON(reg *Registry, peerList *discovery.PeerList, sentinelID string, staleAfter time.Duration) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		peers := peerList.Snapshot()
		rows := make([]peerStatusRow, 0, len(peers))

		for _, p := range peers {
			k := p.Key()
			st := reg.Get(k)
			if st == nil {
				st = NewState()
			}
			snap := st.Snapshot(staleAfter)
			rows = append(rows, peerStatusRow{
				Key:          k,
				Name:         p.Name,
				SentinelID:   p.SentinelID,
				HeartbeatURL: p.HeartbeatURL,
				Status:       snap.Status,
				LastSeen:     snap.LastSeen,
				UptimeSec:    int(snap.Uptime.Seconds()),
			})
		}

		resp := map[string]interface{}{
			"sentinel_id":    sentinelID,
			"uptime_seconds": int(reg.Uptime().Seconds()),
			"peers":          rows,
		}

		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(resp)
	}
}
