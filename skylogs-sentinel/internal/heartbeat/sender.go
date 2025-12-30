package heartbeat

import (
	"context"
	"net/http"
	"time"
)

type Sender struct {
	Client *http.Client
	Target string
	State  *State
}

func NewSender(target string, state *State, timeout time.Duration) *Sender {
	return &Sender{
		Target: target,
		State:  state,
		Client: &http.Client{Timeout: timeout},
	}
}

func (s *Sender) Send(ctx context.Context) error {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, s.Target, nil)
	if err != nil {
		return err
	}

	resp, err := s.Client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	s.State.MarkSeen()
	return nil
}
