package main

import (
	"context"
	"encoding/json"
	"fmt"
	"net"
	"net/http"
	"net/http/httptest"
	"path/filepath"
	"sync"
	"testing"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/config"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/security"
)

func TestBootstrapPeerList_mainRoleUsesConfigPeers(t *testing.T) {
	t.Parallel()
	var cfg config.Config
	cfg.Sentinel.Role = "main"
	cfg.Sentinel.ID = "main-node"
	cfg.Peers = []discovery.Peer{
		{Name: "collector-1", Type: "collector", SentinelID: "c1", HeartbeatURL: "http://c1/hb"},
		{Name: "edge-1", Type: "edge", SentinelID: "e1", HeartbeatURL: "http://e1/hb"},
	}
	raw, label := bootstrapPeerList(&cfg)
	if label != "main/standalone from config.peers" {
		t.Fatalf("label: %q", label)
	}
	if len(raw) != 2 {
		t.Fatalf("want 2 peers, got %d", len(raw))
	}
}

func TestBootstrapPeerList_agent_mainReachable_writesCache(t *testing.T) {
	t.Parallel()
	secret := "shared"
	authoritative := []discovery.Peer{
		{Name: "main-1", Type: "main", SentinelID: "main-a", HeartbeatURL: "http://main/hb"},
		{Name: "collector-1", Type: "collector", SentinelID: "col-1", HeartbeatURL: "http://col/hb"},
		{Name: "edge-1", Type: "edge", SentinelID: "edge-1", HeartbeatURL: "http://edge/hb"},
	}
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != discovery.ClusterPeersPath {
			http.NotFound(w, r)
			return
		}
		tsStr := r.Header.Get("X-SkyLogs-Timestamp")
		sig := r.Header.Get("X-SkyLogs-Signature")
		msg := fmt.Sprintf("%s|%s|%s", r.Method, r.URL.Path, tsStr)
		if !security.Verify(secret, msg, sig) {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(discovery.ClusterPeersResponse{Peers: authoritative, UpdatedAt: time.Now().UTC()})
	}))
	t.Cleanup(srv.Close)

	cachePath := filepath.Join(t.TempDir(), "peers-cache.json")
	var cfg config.Config
	cfg.Sentinel.Role = "agent"
	cfg.Sentinel.ID = "agent-self"
	cfg.MainSentinel.BaseURL = srv.URL
	cfg.MainSentinel.CacheFile = cachePath
	cfg.Security.SharedSecret = secret
	cfg.Heartbeat.Timeout = 5 * time.Second

	raw, label := bootstrapPeerList(&cfg)
	if label != "agent: fetched from main sentinel" {
		t.Fatalf("label: %q", label)
	}
	if len(raw) != len(authoritative) {
		t.Fatalf("want %d peers got %d", len(authoritative), len(raw))
	}
	cached, err := discovery.LoadPeersCache(cachePath)
	if err != nil {
		t.Fatal(err)
	}
	if len(cached) != len(authoritative) {
		t.Fatalf("cache len: got %d want %d", len(cached), len(authoritative))
	}
}

func TestBootstrapPeerList_agent_mainDown_usesDiskCache(t *testing.T) {
	t.Parallel()
	cachePath := filepath.Join(t.TempDir(), "peers-cache.json")
	cachedPeers := []discovery.Peer{
		{Name: "collector-1", Type: "collector", SentinelID: "c1", HeartbeatURL: "http://c1/hb"},
		{Name: "edge-1", Type: "edge", SentinelID: "e1", HeartbeatURL: "http://e1/hb"},
	}
	if err := discovery.SavePeersCache(cachePath, cachedPeers); err != nil {
		t.Fatal(err)
	}

	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	addr := ln.Addr().String()
	_ = ln.Close()

	var cfg config.Config
	cfg.Sentinel.Role = "agent"
	cfg.Sentinel.ID = "agent-self"
	cfg.MainSentinel.BaseURL = "http://" + addr
	cfg.MainSentinel.CacheFile = cachePath
	cfg.Security.SharedSecret = "x"
	cfg.Heartbeat.Timeout = 200 * time.Millisecond

	raw, label := bootstrapPeerList(&cfg)
	if label != "agent: disk cache" {
		t.Fatalf("want disk cache label, got %q", label)
	}
	if len(raw) != 2 {
		t.Fatalf("want 2 cached peers, got %d %+v", len(raw), raw)
	}
}

func TestBootstrapPeerList_agent_mainDown_configFallback(t *testing.T) {
	t.Parallel()
	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	addr := ln.Addr().String()
	_ = ln.Close()

	fallback := []discovery.Peer{
		{Name: "collector-fallback", Type: "collector", SentinelID: "cf1", HeartbeatURL: "http://cf/hb"},
	}
	var cfg config.Config
	cfg.Sentinel.Role = "agent"
	cfg.Sentinel.ID = "agent-self"
	cfg.MainSentinel.BaseURL = "http://" + addr
	cfg.MainSentinel.CacheFile = filepath.Join(t.TempDir(), "missing.json")
	cfg.Peers = fallback
	cfg.Security.SharedSecret = "x"
	cfg.Heartbeat.Timeout = 200 * time.Millisecond

	raw, label := bootstrapPeerList(&cfg)
	if label != "agent: config fallback" {
		t.Fatalf("label: %q", label)
	}
	if len(raw) != 1 || raw[0].SentinelID != "cf1" {
		t.Fatalf("unexpected raw: %+v", raw)
	}
}

func TestBootstrapPeerList_agent_mainDown_noCacheNoConfig(t *testing.T) {
	t.Parallel()
	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	addr := ln.Addr().String()
	_ = ln.Close()

	var cfg config.Config
	cfg.Sentinel.Role = "agent"
	cfg.Sentinel.ID = "agent-self"
	cfg.MainSentinel.BaseURL = "http://" + addr
	cfg.MainSentinel.CacheFile = ""
	cfg.Security.SharedSecret = "x"
	cfg.Heartbeat.Timeout = 200 * time.Millisecond

	raw, label := bootstrapPeerList(&cfg)
	if label != "agent: empty peer list" {
		t.Fatalf("label: %q", label)
	}
	if len(raw) != 0 {
		t.Fatalf("want empty, got %+v", raw)
	}
}

func TestAgentClusterSync_refreshUpdatesPeerList(t *testing.T) {
	t.Parallel()
	secret := "s"
	peersV1 := []discovery.Peer{
		{Name: "collector-1", Type: "collector", SentinelID: "c1", HeartbeatURL: "http://c1/hb"},
	}
	peersV2 := []discovery.Peer{
		{Name: "collector-1", Type: "collector", SentinelID: "c1", HeartbeatURL: "http://c1/hb"},
		{Name: "edge-1", Type: "edge", SentinelID: "e1", HeartbeatURL: "http://e1/hb"},
	}
	var mu sync.Mutex
	var round int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != discovery.ClusterPeersPath {
			http.NotFound(w, r)
			return
		}
		tsStr := r.Header.Get("X-SkyLogs-Timestamp")
		sig := r.Header.Get("X-SkyLogs-Signature")
		msg := fmt.Sprintf("%s|%s|%s", r.Method, r.URL.Path, tsStr)
		if !security.Verify(secret, msg, sig) {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}
		mu.Lock()
		rn := round
		round++
		mu.Unlock()
		w.Header().Set("Content-Type", "application/json")
		if rn == 0 {
			_ = json.NewEncoder(w).Encode(discovery.ClusterPeersResponse{Peers: peersV1, UpdatedAt: time.Now().UTC()})
			return
		}
		_ = json.NewEncoder(w).Encode(discovery.ClusterPeersResponse{Peers: peersV2, UpdatedAt: time.Now().UTC()})
	}))
	t.Cleanup(srv.Close)

	pl := discovery.NewPeerList()
	t.Cleanup(pl.Close)

	cachePath := filepath.Join(t.TempDir(), "sync-cache.json")
	var cfg config.Config
	cfg.Sentinel.Role = "agent"
	cfg.Sentinel.ID = "agent-self"
	cfg.Sentinel.SelfInstanceName = ""
	cfg.MainSentinel.BaseURL = srv.URL
	cfg.MainSentinel.RefreshInterval = 40 * time.Millisecond
	cfg.MainSentinel.CacheFile = cachePath
	cfg.Security.SharedSecret = secret
	cfg.Heartbeat.Timeout = 2 * time.Second

	ctx, cancel := context.WithCancel(context.Background())
	var wg sync.WaitGroup
	wg.Add(1)
	go agentClusterSync(ctx, &wg, &cfg, pl)

	waitUntil(t, 2*time.Second, func() bool {
		return len(pl.Snapshot()) >= 1
	})
	waitUntil(t, 2*time.Second, func() bool {
		return len(pl.Snapshot()) >= 2
	})

	cancel()
	wg.Wait()

	cached, err := discovery.LoadPeersCache(cachePath)
	if err != nil {
		t.Fatal(err)
	}
	if len(cached) != 2 {
		t.Fatalf("want cache to reflect last successful refresh (2 peers), got %d %+v", len(cached), cached)
	}
}

func waitUntil(t *testing.T, timeout time.Duration, cond func() bool) {
	t.Helper()
	deadline := time.Now().Add(timeout)
	for time.Now().Before(deadline) {
		if cond() {
			return
		}
		time.Sleep(5 * time.Millisecond)
	}
	t.Fatal("condition not met within", timeout)
}