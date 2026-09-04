package cluster

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/security"
)

// PeersHandler serves the authoritative peer list (main sentinel only). Auth matches heartbeat: signed GET.
func PeersHandler(secret string, maxSkew time.Duration, snapshot func() []discovery.Peer) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodGet {
			http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
			return
		}

		tsStr := r.Header.Get("X-SkyLogs-Timestamp")
		sig := r.Header.Get("X-SkyLogs-Signature")

		if tsStr == "" || sig == "" {
			http.Error(w, "missing auth headers", http.StatusUnauthorized)
			return
		}

		ts, err := strconv.ParseInt(tsStr, 10, 64)
		if err != nil {
			http.Error(w, "invalid timestamp", http.StatusUnauthorized)
			return
		}

		t := time.Unix(ts, 0)
		if time.Since(t) > maxSkew || time.Until(t) > maxSkew {
			http.Error(w, "timestamp skew", http.StatusUnauthorized)
			return
		}

		message := fmt.Sprintf("%s|%s|%s", r.Method, r.URL.Path, tsStr)
		if !security.Verify(secret, message, sig) {
			http.Error(w, "invalid signature", http.StatusUnauthorized)
			return
		}

		peers := snapshot()
		out := make([]discovery.Peer, len(peers))
		copy(out, peers)

		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(discovery.ClusterPeersResponse{
			Peers:     out,
			UpdatedAt: time.Now().UTC(),
		})
	}
}
