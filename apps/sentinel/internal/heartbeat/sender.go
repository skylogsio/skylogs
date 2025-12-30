package heartbeat

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"
	"time"

	"github.com/skylogsio/skylogs/skylogs-sentinel/pkg/model"
)

type Sender struct {
	PeerURL string
	DC      string
	Node    string
	Client  *http.Client
}

func (s *Sender) Run(ctx context.Context, interval time.Duration) {
	ticker := time.NewTicker(interval)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			s.send()
		case <-ctx.Done():
			return
		}
	}
}

func (s *Sender) send() {
	hb := model.Heartbeat{
		DC:        s.DC,
		Node:      s.Node,
		Timestamp: time.Now().UTC(),
	}

	data, _ := json.Marshal(hb)

	req, _ := http.NewRequest(
		http.MethodPost,
		s.PeerURL,
		bytes.NewBuffer(data),
	)

	req.Header.Set("Content-Type", "application/json")
	s.Client.Do(req) // errors tolerated
}

