package heartbeat

import (
	"fmt"
	"net/http"
	"strconv"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/security"
)

const maxSkew = 10 * time.Second

func Receiver(state *State, secret string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
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

		message := fmt.Sprintf(
			"%s|%s|%s",
			r.Method,
			r.URL.Path,
			tsStr,
		)

		if !security.Verify(secret, message, sig) {
			http.Error(w, "invalid signature", http.StatusUnauthorized)
			return
		}

		state.MarkSeen()
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("ok"))
	}
}
