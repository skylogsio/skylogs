package heartbeat

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
)

func TestRunPeerWatcher_failingPeerSendsAlert(t *testing.T) {
	t.Parallel()

	mux := http.NewServeMux()
	mux.HandleFunc("/heartbeat", func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "down", http.StatusServiceUnavailable)
	})
	srv := httptest.NewServer(mux)
	t.Cleanup(srv.Close)

	alertCh := make(chan string, 4)
	alerts := AlertHandler{
		Fire: func(ctx context.Context, instanceName, desc string) error {
			alertCh <- desc
			return nil
		},
	}

	wc := WatcherConfig{
		Interval:  40 * time.Millisecond,
		Timeout:   50 * time.Millisecond,
		FailAfter: 10 * time.Millisecond,
		Secret:    "watcher-secret",
		SelfID:    "watcher-test",
	}
	peer := discovery.Peer{
		Name:         "fail-peer",
		Type:         "collector",
		SentinelID:   "fail-1",
		HeartbeatURL: srv.URL + "/heartbeat",
	}

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	go RunPeerWatcher(ctx, peer, NewRegistry(), wc, alerts)

	select {
	case msg := <-alertCh:
		if !strings.Contains(msg, "fail-peer") {
			t.Fatalf("alert should mention peer name: %q", msg)
		}
		if !strings.Contains(msg, "watcher-test") {
			t.Fatalf("alert should mention reporter: %q", msg)
		}
	case <-time.After(2 * time.Second):
		t.Fatal("timed out waiting for alert")
	}

	cancel()
	time.Sleep(100 * time.Millisecond)
}
