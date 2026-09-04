package heartbeat

import (
	"fmt"
	"net/http"
	"net/http/httptest"
	"strconv"
	"testing"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/security"
)

func TestReceiver_validRequestMarksSeen(t *testing.T) {
	t.Parallel()
	reg := NewRegistry()
	secret := "recv-secret"
	maxSkew := 30 * time.Second
	h := Receiver(reg, secret, maxSkew)

	path := "/heartbeat"
	ts := strconv.FormatInt(time.Now().Unix(), 10)
	msg := fmt.Sprintf("GET|%s|%s", path, ts)
	sig := security.Sign(secret, msg)

	req := httptest.NewRequest(http.MethodGet, path, nil)
	req.Header.Set("X-SkyLogs-Timestamp", ts)
	req.Header.Set("X-SkyLogs-Signature", sig)
	req.Header.Set(sentinelIDHeader, "remote-peer-1")

	rr := httptest.NewRecorder()
	h(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("status %d body %s", rr.Code, rr.Body.String())
	}
	st := reg.Get("remote-peer-1")
	if st == nil {
		t.Fatal("expected registry entry for remote-peer-1")
	}
	if st.Snapshot(5*time.Second).Status != "healthy" {
		t.Fatalf("want healthy, got %+v", st.Snapshot(5*time.Second))
	}
}

func TestReceiver_missingAuthHeaders(t *testing.T) {
	t.Parallel()
	reg := NewRegistry()
	h := Receiver(reg, "s", 30*time.Second)

	req := httptest.NewRequest(http.MethodGet, "/heartbeat", nil)
	req.Header.Set(sentinelIDHeader, "x")
	rr := httptest.NewRecorder()
	h(rr, req)

	if rr.Code == http.StatusOK {
		t.Fatal("expected non-200")
	}
	if reg.Get("x") != nil {
		t.Fatal("should not create state on failed auth")
	}
}

func TestReceiver_badSignature(t *testing.T) {
	t.Parallel()
	reg := NewRegistry()
	h := Receiver(reg, "correct-secret", 30*time.Second)

	path := "/heartbeat"
	ts := strconv.FormatInt(time.Now().Unix(), 10)
	req := httptest.NewRequest(http.MethodGet, path, nil)
	req.Header.Set("X-SkyLogs-Timestamp", ts)
	req.Header.Set("X-SkyLogs-Signature", "not-a-valid-signature")
	req.Header.Set(sentinelIDHeader, "bad-sig-peer")

	rr := httptest.NewRecorder()
	h(rr, req)

	if rr.Code == http.StatusOK {
		t.Fatal("expected non-200 for bad signature")
	}
}

func TestReceiver_staleTimestamp(t *testing.T) {
	t.Parallel()
	reg := NewRegistry()
	secret := "recv-secret"
	maxSkew := 2 * time.Second
	h := Receiver(reg, secret, maxSkew)

	path := "/heartbeat"
	ts := strconv.FormatInt(time.Now().Add(-10*time.Minute).Unix(), 10)
	msg := fmt.Sprintf("GET|%s|%s", path, ts)
	sig := security.Sign(secret, msg)

	req := httptest.NewRequest(http.MethodGet, path, nil)
	req.Header.Set("X-SkyLogs-Timestamp", ts)
	req.Header.Set("X-SkyLogs-Signature", sig)
	req.Header.Set(sentinelIDHeader, "stale-peer")

	rr := httptest.NewRecorder()
	h(rr, req)

	if rr.Code == http.StatusOK {
		t.Fatal("expected non-200 for stale timestamp")
	}
}
