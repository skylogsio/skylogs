package discovery

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/security"
)

func TestFetchClusterPeers_success(t *testing.T) {
	t.Parallel()
	secret := "test-secret"
	peers := []Peer{
		{Name: "collector-1", Type: "collector", SentinelID: "c1", HeartbeatURL: "http://c1/hb"},
		{Name: "edge-2", Type: "edge", SentinelID: "e2", HeartbeatURL: "http://e2/hb"},
	}
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != ClusterPeersPath {
			http.NotFound(w, r)
			return
		}
		tsStr := r.Header.Get("X-SkyLogs-Timestamp")
		sig := r.Header.Get("X-SkyLogs-Signature")
		if tsStr == "" || sig == "" {
			http.Error(w, "missing auth", http.StatusUnauthorized)
			return
		}
		msg := fmt.Sprintf("%s|%s|%s", r.Method, r.URL.Path, tsStr)
		if !security.Verify(secret, msg, sig) {
			http.Error(w, "bad sig", http.StatusUnauthorized)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(ClusterPeersResponse{Peers: peers, UpdatedAt: time.Now().UTC()})
	}))
	t.Cleanup(srv.Close)

	ctx := context.Background()
	got, err := FetchClusterPeers(ctx, srv.URL, "", secret, "agent-under-test", 5*time.Second)
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != 2 {
		t.Fatalf("want 2 peers got %d %+v", len(got), got)
	}
}

func TestFetchClusterPeers_nonOK(t *testing.T) {
	t.Parallel()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "nope", http.StatusServiceUnavailable)
	}))
	t.Cleanup(srv.Close)

	_, err := FetchClusterPeers(context.Background(), srv.URL, "", "s", "id", 2*time.Second)
	if err == nil {
		t.Fatal("expected error for non-200")
	}
}

func TestFetchClusterPeers_badJSON(t *testing.T) {
	t.Parallel()
	secret := "x"
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		tsStr := r.Header.Get("X-SkyLogs-Timestamp")
		sig := r.Header.Get("X-SkyLogs-Signature")
		msg := fmt.Sprintf("%s|%s|%s", r.Method, r.URL.Path, tsStr)
		if !security.Verify(secret, msg, sig) {
			http.Error(w, "bad sig", http.StatusUnauthorized)
			return
		}
		_, _ = w.Write([]byte("not-json"))
	}))
	t.Cleanup(srv.Close)

	_, err := FetchClusterPeers(context.Background(), srv.URL, "", secret, "", 2*time.Second)
	if err == nil {
		t.Fatal("expected decode error")
	}
}
