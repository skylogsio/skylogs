package heartbeat

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"sync/atomic"
	"testing"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
)

func TestRunPeerWatcher_recoveredPeerSendsResolve(t *testing.T) {
	t.Parallel()

	var down atomic.Bool
	down.Store(true)

	mux := http.NewServeMux()
	mux.HandleFunc("/heartbeat", func(w http.ResponseWriter, r *http.Request) {
		if down.Load() {
			http.Error(w, "down", http.StatusServiceUnavailable)
			return
		}
		w.WriteHeader(http.StatusOK)
	})
	srv := httptest.NewServer(mux)
	t.Cleanup(srv.Close)

	fireCh := make(chan string, 2)
	resolveCh := make(chan string, 2)
	alerts := AlertHandler{
		Fire: func(ctx context.Context, instanceName, desc string) error {
			fireCh <- instanceName
			return nil
		},
		Resolve: func(ctx context.Context, instanceName, desc string) error {
			resolveCh <- instanceName
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
		Name:         "recover-peer",
		Type:         "collector",
		SentinelID:   "recover-1",
		HeartbeatURL: srv.URL + "/heartbeat",
	}

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	go RunPeerWatcher(ctx, peer, NewRegistry(), wc, alerts)

	select {
	case instance := <-fireCh:
		if instance != "recover-peer" {
			t.Fatalf("fire instance: %q", instance)
		}
	case <-time.After(2 * time.Second):
		t.Fatal("timed out waiting for fire alert")
	}

	down.Store(false)

	select {
	case instance := <-resolveCh:
		if instance != "recover-peer" {
			t.Fatalf("resolve instance: %q", instance)
		}
	case <-time.After(2 * time.Second):
		t.Fatal("timed out waiting for resolve alert")
	}

	cancel()
	time.Sleep(100 * time.Millisecond)
}

func TestRunPeerWatcher_resolveRetriesUntilSuccess(t *testing.T) {
	t.Parallel()

	mux := http.NewServeMux()
	mux.HandleFunc("/heartbeat", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})
	srv := httptest.NewServer(mux)
	t.Cleanup(srv.Close)

	peer := discovery.Peer{
		Name:         "retry-peer",
		Type:         "collector",
		SentinelID:   "retry-1",
		HeartbeatURL: srv.URL + "/heartbeat",
	}
	reg := NewRegistry()
	st := reg.GetOrCreate(peer.Key())
	st.MarkUnhealthyIfNeeded()

	var resolveAttempts atomic.Int32
	alerts := AlertHandler{
		Fire: func(context.Context, string, string) error { return nil },
		Resolve: func(ctx context.Context, instanceName, desc string) error {
			if resolveAttempts.Add(1) == 1 {
				return errors.New("resolve failed")
			}
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
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	go RunPeerWatcher(ctx, peer, reg, wc, alerts)

	deadline := time.After(2 * time.Second)
	for {
		if resolveAttempts.Load() >= 2 && !st.NeedsResolve() {
			break
		}
		select {
		case <-deadline:
			t.Fatalf("resolve attempts=%d needsResolve=%v", resolveAttempts.Load(), st.NeedsResolve())
		case <-time.After(20 * time.Millisecond):
		}
	}

	cancel()
}
