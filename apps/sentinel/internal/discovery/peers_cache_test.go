package discovery

import (
	"path/filepath"
	"testing"
	"time"
)

func TestSaveLoadPeersCache_roundTrip(t *testing.T) {
	t.Parallel()
	dir := t.TempDir()
	path := filepath.Join(dir, "peers.json")

	peers := []Peer{
		{Name: "collector-1", Type: "collector", SentinelID: "s-col-1", HeartbeatURL: "http://a/hb"},
		{Name: "edge-1", Type: "edge", SentinelID: "s-edge-1", HeartbeatURL: "http://b/hb"},
	}
	if err := SavePeersCache(path, peers); err != nil {
		t.Fatal(err)
	}
	got, err := LoadPeersCache(path)
	if err != nil {
		t.Fatal(err)
	}
	if len(got) != len(peers) {
		t.Fatalf("len got %d want %d", len(got), len(peers))
	}
	for i := range peers {
		if got[i].Name != peers[i].Name || got[i].Type != peers[i].Type || got[i].SentinelID != peers[i].SentinelID {
			t.Fatalf("peer %d mismatch:\n got %+v\nwant %+v", i, got[i], peers[i])
		}
	}
}

func TestLoadPeersCache_emptyPath(t *testing.T) {
	t.Parallel()
	if _, err := LoadPeersCache(""); err == nil {
		t.Fatal("expected error for empty path")
	}
}

func TestSavePeersCache_emptyPath(t *testing.T) {
	t.Parallel()
	if err := SavePeersCache("", nil); err == nil {
		t.Fatal("expected error for empty path")
	}
}

func TestSavePeersCache_createsParentDir(t *testing.T) {
	t.Parallel()
	dir := filepath.Join(t.TempDir(), "nested", "cache")
	path := filepath.Join(dir, "peers.json")
	peers := []Peer{{Name: "a", SentinelID: "s1", HeartbeatURL: "http://a/hb"}}
	if err := SavePeersCache(path, peers); err != nil {
		t.Fatal(err)
	}
	if _, err := LoadPeersCache(path); err != nil {
		t.Fatal(err)
	}
}

func TestCachedPeersFile_JSONEnvelope(t *testing.T) {
	t.Parallel()
	// Document on-disk shape used for agent fallback when main is unreachable.
	c := CachedPeersFile{
		Peers: []Peer{
			{Name: "main", Type: "main", SentinelID: "m1", HeartbeatURL: "http://m/hb"},
		},
		FetchedAt: time.Date(2026, 5, 1, 12, 0, 0, 0, time.UTC),
	}
	dir := t.TempDir()
	path := filepath.Join(dir, "cache.json")
	if err := SavePeersCache(path, c.Peers); err != nil {
		t.Fatal(err)
	}
	loaded, err := LoadPeersCache(path)
	if err != nil {
		t.Fatal(err)
	}
	if len(loaded) != 1 || loaded[0].SentinelID != "m1" {
		t.Fatalf("unexpected load: %+v", loaded)
	}
}
