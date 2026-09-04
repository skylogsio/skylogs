package heartbeat

import (
	"context"
	"fmt"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/security"
)

func heartbeatEchoHandler(secret string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/heartbeat" || r.Method != http.MethodGet {
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
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte("ok"))
	}
}

func TestSender_Send_successUpdatesState(t *testing.T) {
	t.Parallel()
	secret := "probe-secret"
	srv := httptest.NewServer(heartbeatEchoHandler(secret))
	t.Cleanup(srv.Close)

	st := NewState()
	sender := NewSender(srv.URL+"/heartbeat", st, "caller-1", secret, 2*time.Second)

	if err := sender.Send(context.Background()); err != nil {
		t.Fatal(err)
	}
	if st.Snapshot(5 * time.Second).Status != "healthy" {
		t.Fatalf("want healthy snapshot, got %+v", st.Snapshot(5*time.Second))
	}
	if st.TimeSinceLastSeen() > 500*time.Millisecond {
		t.Fatalf("expected recent MarkSeen, got TimeSinceLastSeen=%v", st.TimeSinceLastSeen())
	}
}

func TestSender_Send_nonOKReturnsError(t *testing.T) {
	t.Parallel()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "down", http.StatusServiceUnavailable)
	}))
	t.Cleanup(srv.Close)

	st := NewState()
	sender := NewSender(srv.URL+"/heartbeat", st, "caller-1", "x", 2*time.Second)

	err := sender.Send(context.Background())
	if err == nil {
		t.Fatal("expected error for 503")
	}
	if !strings.Contains(err.Error(), "heartbeat failed") {
		t.Fatalf("unexpected error: %v", err)
	}
}
