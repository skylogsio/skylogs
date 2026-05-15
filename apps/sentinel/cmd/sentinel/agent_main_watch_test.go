package main

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/config"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/heartbeat"
)

func TestPeersForAgentWatch_includesMainHeartbeatPeer(t *testing.T) {
	t.Parallel()
	var cfg config.Config
	cfg.Sentinel.Role = "agent"
	cfg.Sentinel.ID = "agent-1"
	cfg.MainSentinel.BaseURL = "http://main.example:9191"
	cfg.MainSentinel.Name = "cluster-main"

	got := peersForAgentWatch(&cfg, nil)
	if len(got) != 1 {
		t.Fatalf("want 1 peer, got %d %+v", len(got), got)
	}
	if got[0].Name != "cluster-main" {
		t.Fatalf("name: %q", got[0].Name)
	}
	if got[0].HeartbeatURL != "http://main.example:9191/heartbeat" {
		t.Fatalf("url: %q", got[0].HeartbeatURL)
	}
}

func TestAgentMainDown_sendsWebhookLikePeerDown(t *testing.T) {
	t.Parallel()

	mux := http.NewServeMux()
	mux.HandleFunc("/heartbeat", func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "down", http.StatusServiceUnavailable)
	})
	srv := httptest.NewServer(mux)
	t.Cleanup(srv.Close)

	var cfg config.Config
	cfg.Sentinel.Role = "agent"
	cfg.Sentinel.ID = "agent-zone-b"
	cfg.MainSentinel.BaseURL = srv.URL
	cfg.MainSentinel.Name = "main-sentinel"
	cfg.Security.SharedSecret = "secret"
	cfg.Heartbeat.Interval = 40 * time.Millisecond
	cfg.Heartbeat.Timeout = 50 * time.Millisecond
	cfg.Heartbeat.FailAfter = 10 * time.Millisecond

	alertCh := make(chan string, 2)
	sendAlert := func(ctx context.Context, instance, desc string) error {
		if instance != "main-sentinel" {
			t.Fatalf("instance: %q", instance)
		}
		alertCh <- desc
		return nil
	}

	reg := heartbeat.NewRegistry()
	wc := heartbeat.WatcherConfig{
		Interval:  cfg.Heartbeat.Interval,
		Timeout:   cfg.Heartbeat.Timeout,
		FailAfter: cfg.Heartbeat.FailAfter,
		Secret:    cfg.Security.SharedSecret,
		SelfID:    cfg.Sentinel.ID,
	}
	sup := heartbeat.NewSupervisor(reg, wc, sendAlert)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	var wg sync.WaitGroup
	wg.Add(1)
	go func() {
		defer wg.Done()
		sup.Reconcile(ctx, peersForAgentWatch(&cfg, nil))
		<-ctx.Done()
	}()

	select {
	case msg := <-alertCh:
		if !strings.Contains(msg, "main-sentinel") {
			t.Fatalf("alert should mention main: %q", msg)
		}
		if !strings.Contains(msg, "agent-zone-b") {
			t.Fatalf("alert should mention reporter: %q", msg)
		}
	case <-time.After(2 * time.Second):
		t.Fatal("timed out waiting for main-down webhook")
	}

	cancel()
	sup.Shutdown()
	wg.Wait()
}
