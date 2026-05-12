package heartbeat

import (
	"context"
	"fmt"
	"log"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
)

// WatcherConfig drives outbound heartbeat checks for one peer.
type WatcherConfig struct {
	Interval  time.Duration
	Timeout   time.Duration
	FailAfter time.Duration
	Secret    string
	SelfID    string
}

// RunPeerWatcher periodically GETs the peer's /heartbeat and fires sendAlert when stale.
func RunPeerWatcher(
	ctx context.Context,
	peer discovery.Peer,
	reg *Registry,
	wc WatcherConfig,
	sendAlert func(context.Context, string, string) error,
) {
	st := reg.GetOrCreate(peer.Key())
	sender := NewSender(peer.HeartbeatURL, st, wc.SelfID, wc.Secret, wc.Timeout)

	ticker := time.NewTicker(wc.Interval)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			if err := sender.Send(ctx); err != nil {
				if st.TimeSinceLastSeen() > wc.FailAfter {
					if st.MarkUnhealthyIfNeeded() {
						desc := fmt.Sprintf(
							"No heartbeat from %s for more than %s (reported by %s)",
							peer.Name,
							wc.FailAfter,
							wc.SelfID,
						)
						if err := sendAlert(ctx, peer.Name, desc); err != nil {
							log.Println("failed to send webhook:", err)
						}
					}
				}
			}
		case <-ctx.Done():
			return
		}
	}
}
