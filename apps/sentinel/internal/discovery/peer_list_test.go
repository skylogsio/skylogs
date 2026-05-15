package discovery

import (
	"testing"
)

func TestFilterPeers_multiTypeAndSelf(t *testing.T) {
	t.Parallel()

	all := []Peer{
		{Name: "main-1", Type: "main", SentinelID: "main-a", HeartbeatURL: "http://main/hb"},
		{Name: "collector-east", Type: "collector", SentinelID: "col-1", HeartbeatURL: "http://c1/hb"},
		{Name: "edge-west", Type: "edge", SentinelID: "edge-1", HeartbeatURL: "http://e1/hb"},
		{Name: "no-heartbeat", Type: "collector", SentinelID: "bad-1"},
	}

	t.Run("filters self by sentinel id", func(t *testing.T) {
		t.Parallel()
		got := FilterPeers(all, "", "edge-1")
		// no-heartbeat peer is always dropped; self edge removed → main + collector.
		if len(got) != 2 {
			t.Fatalf("want 2 peers after removing self and invalid peers, got %d: %+v", len(got), got)
		}
		for _, p := range got {
			if p.SentinelID == "edge-1" {
				t.Fatalf("self should be removed: %+v", p)
			}
		}
	})

	t.Run("filters self by instance name", func(t *testing.T) {
		t.Parallel()
		got := FilterPeers(all, "main-1", "")
		// Drops main-1 and no-heartbeat → collector + edge.
		if len(got) != 2 {
			t.Fatalf("want 2 peers, got %d", len(got))
		}
		for _, p := range got {
			if p.Name == "main-1" {
				t.Fatalf("self should be removed: %+v", p)
			}
		}
	})

	t.Run("drops peers without heartbeat url", func(t *testing.T) {
		t.Parallel()
		got := FilterPeers(all, "", "")
		if len(got) != 3 {
			t.Fatalf("want 3 peers with heartbeat URLs, got %d", len(got))
		}
	})
}

func TestPeer_Key_prefersSentinelID(t *testing.T) {
	t.Parallel()
	p := Peer{Name: "dup", SentinelID: "real-id"}
	if p.Key() != "real-id" {
		t.Fatalf("Key: got %q want real-id", p.Key())
	}
	p2 := Peer{Name: "only-name"}
	if p2.Key() != "only-name" {
		t.Fatalf("Key: got %q want only-name", p2.Key())
	}
}
