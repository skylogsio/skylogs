package heartbeat

import (
	"context"
	"fmt"
	"net/http"
	"strconv"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/security"
)

type Sender struct {
	Client *http.Client
	Target string
	State  *State
	Secret string
}

func NewSender(target string, state *State, secret string, timeout time.Duration) *Sender {
	return &Sender{
		Client: &http.Client{Timeout: timeout},
		Target: target,
		State:  state,
		Secret: secret,
	}
}

func (s *Sender) Send(ctx context.Context) error {
	ts := strconv.FormatInt(time.Now().Unix(), 10)

	message := fmt.Sprintf("POST|/heartbeat|%s", ts)
	signature := security.Sign(s.Secret, message)
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, s.Target, nil)
	if err != nil {
		return err
	}
	req.Header.Set("X-SkyLogs-Timestamp", ts)
	req.Header.Set("X-SkyLogs-Signature", signature)

	resp, err := s.Client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	s.State.MarkSeen()
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("heartbeat failed: %s", resp.Status)
	}
	return nil
}
