package heartbeat

import (
	"encoding/json"
	"net/http"
)

func StatusHandler(state *State, sentinelID string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		snap := state.Snapshot()

		resp := map[string]interface{}{
			"sentinel_id":    sentinelID,
			"status":         snap.Status,
			"last_seen":      snap.LastSeen,
			"failure_count":  snap.FailureCount,
			"uptime_seconds": int(snap.Uptime.Seconds()),
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(resp)
	}
}

