package heartbeat

import (
	"context"
	"testing"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
)

func TestSupervisor_Reconcile_addRemoveAndURLChange(t *testing.T) {
	t.Parallel()
	reg := NewRegistry()
	sendAlert := func(context.Context, string, string) error {
		return nil
	}
	wc := WatcherConfig{
		Interval:  time.Hour,
		Timeout:   time.Second,
		FailAfter: time.Minute,
		Secret:    "x",
		SelfID:    "supervisor-test",
	}
	sup := NewSupervisor(reg, wc, sendAlert)
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	mainPeer := discovery.Peer{Name: "main-1", Type: "main", SentinelID: "m1", HeartbeatURL: "http://m1/hb"}
	colPeer := discovery.Peer{Name: "collector-1", Type: "collector", SentinelID: "c1", HeartbeatURL: "http://c1/hb"}
	edgePeer := discovery.Peer{Name: "edge-1", Type: "edge", SentinelID: "e1", HeartbeatURL: "http://e1/hb"}

	// Start three watchers (main + two agent types).
	sup.Reconcile(ctx, []discovery.Peer{mainPeer, colPeer, edgePeer})

	// One sentinel "gone" from desired set: only main + collector.
	sup.Reconcile(ctx, []discovery.Peer{mainPeer, colPeer})

	// Two sentinels gone: only main remains.
	sup.Reconcile(ctx, []discovery.Peer{mainPeer})

	// Main "gone" from peer list: empty desired set cancels all outbound watchers.
	sup.Reconcile(ctx, nil)

	// URL change for same key restarts watcher (no panic).
	colPeerV2 := colPeer
	colPeerV2.HeartbeatURL = "http://c1-v2/hb"
	sup.Reconcile(ctx, []discovery.Peer{colPeerV2})
	sup.Reconcile(ctx, []discovery.Peer{colPeer})

	cancel()
	sup.Shutdown()
}
