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

// AlertHandler sends fire and resolve webhooks for a peer instance.
type AlertHandler struct {
	Fire    func(context.Context, string, string) error
	Resolve func(context.Context, string, string) error
}

// RunPeerWatcher periodically GETs the peer's /heartbeat and fires/resolves alerts.
func RunPeerWatcher(
	ctx context.Context,
	peer discovery.Peer,
	reg *Registry,
	wc WatcherConfig,
	alerts AlertHandler,
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
						if err := alerts.Fire(ctx, peer.Name, desc); err != nil {
							log.Println("failed to send fire webhook:", err)
						}
					}
				}
			} else if st.NeedsResolve() && alerts.Resolve != nil {
				desc := fmt.Sprintf(
					"Heartbeat from %s recovered (reported by %s)",
					peer.Name,
					wc.SelfID,
				)
				if err := alerts.Resolve(ctx, peer.Name, desc); err != nil {
					log.Println("failed to send resolve webhook:", err)
				} else {
					st.ClearResolvePending()
				}
			}
		case <-ctx.Done():
			return
		}
	}
}
