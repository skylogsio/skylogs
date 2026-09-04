package discovery

import "testing"

func TestAppendMainWatchPeer_addsAndDedupes(t *testing.T) {
	t.Parallel()

	base := "http://main.example:9191"
	got := AppendMainWatchPeer(nil, base, "", "")
	if len(got) != 1 {
		t.Fatalf("want 1 peer, got %d", len(got))
	}
	if got[0].HeartbeatURL != "http://main.example:9191/heartbeat" {
		t.Fatalf("heartbeat url: %q", got[0].HeartbeatURL)
	}
	if got[0].Name != "main" {
		t.Fatalf("default name: %q", got[0].Name)
	}

	existing := []Peer{
		{Name: "main-1", SentinelID: "main-a", HeartbeatURL: "http://main.example:9191/heartbeat"},
	}
	if len(AppendMainWatchPeer(existing, base, "main", "main-a")) != 1 {
		t.Fatal("should not duplicate same heartbeat url")
	}

	byID := []Peer{{Name: "main-1", SentinelID: "main-a", HeartbeatURL: "http://other/hb"}}
	if len(AppendMainWatchPeer(byID, base, "main", "main-a")) != 1 {
		t.Fatal("should not duplicate same sentinel id")
	}

	if AppendMainWatchPeer(nil, "  ", "x", "") != nil {
		t.Fatal("empty base should return input unchanged")
	}
}
