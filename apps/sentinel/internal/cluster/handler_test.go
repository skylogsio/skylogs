package cluster

import (
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"strconv"
	"testing"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/security"
)

func TestPeersHandler_signedGET(t *testing.T) {
	t.Parallel()
	secret := "cluster-secret"
	peers := []discovery.Peer{
		{Name: "collector-1", Type: "collector", SentinelID: "c1", HeartbeatURL: "http://c1/hb"},
		{Name: "edge-1", Type: "edge", SentinelID: "e1", HeartbeatURL: "http://e1/hb"},
	}
	h := PeersHandler(secret, 30*time.Second, func() []discovery.Peer {
		out := make([]discovery.Peer, len(peers))
		copy(out, peers)
		return out
	})
	srv := httptest.NewServer(h)
	t.Cleanup(srv.Close)

	ts := strconv.FormatInt(time.Now().Unix(), 10)
	path := discovery.ClusterPeersPath
	msg := fmt.Sprintf("GET|%s|%s", path, ts)
	sig := security.Sign(secret, msg)
	req, _ := http.NewRequest(http.MethodGet, srv.URL+path, nil)
	req.Header.Set("X-SkyLogs-Timestamp", ts)
	req.Header.Set("X-SkyLogs-Signature", sig)

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatal(err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		t.Fatalf("status %d", resp.StatusCode)
	}
	var body discovery.ClusterPeersResponse
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		t.Fatal(err)
	}
	if len(body.Peers) != 2 {
		t.Fatalf("want 2 peers got %d", len(body.Peers))
	}
}

func TestPeersHandler_rejectsBadSignature(t *testing.T) {
	t.Parallel()
	h := PeersHandler("s", 30*time.Second, func() []discovery.Peer { return nil })
	srv := httptest.NewServer(h)
	t.Cleanup(srv.Close)

	ts := strconv.FormatInt(time.Now().Unix(), 10)
	req, _ := http.NewRequest(http.MethodGet, srv.URL+discovery.ClusterPeersPath, nil)
	req.Header.Set("X-SkyLogs-Timestamp", ts)
	req.Header.Set("X-SkyLogs-Signature", "deadbeef")

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatal(err)
	}
	defer resp.Body.Close()
	if resp.StatusCode == http.StatusOK {
		t.Fatal("expected non-200 for bad signature")
	}
}
