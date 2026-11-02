package model

import (
	"bytes"
	"fmt"
	"io"
	"regexp"
	"strings"
	"sync"
	"time"
)

var raftPeerPattern = regexp.MustCompile(
	`(?:peer|target|address|server)="?([^\s",]+)`,
)

// RaftLogFilter suppresses repeated Raft connection logs.
type RaftLogFilter struct {
	mu       sync.Mutex
	output   io.Writer
	interval time.Duration
	pending  []byte
	last     map[string]time.Time
	skipped  map[string]int
}

// NewRaftLogFilter creates a thread-safe log rate limiter.
func NewRaftLogFilter(output io.Writer, interval time.Duration) *RaftLogFilter {
	return &RaftLogFilter{
		output:   output,
		interval: interval,
		last:     make(map[string]time.Time),
		skipped:  make(map[string]int),
	}
}

// Write processes complete log lines and suppresses repeated matching errors.
func (w *RaftLogFilter) Write(p []byte) (int, error) {
	w.mu.Lock()
	defer w.mu.Unlock()

	w.pending = append(w.pending, p...)

	for {
		index := bytes.IndexByte(w.pending, '\n')
		if index < 0 {
			break
		}

		line := string(w.pending[:index+1])
		w.pending = w.pending[index+1:]

		key, limited := raftLogKey(line)

		if limited && w.interval > 0 {
			now := time.Now()

			if last, exists := w.last[key]; exists {
				if now.Sub(last) < w.interval {
					w.skipped[key]++
					continue
				}
			}

			if skipped := w.skipped[key]; skipped > 0 {
				summary := fmt.Sprintf(
					"[raft] suppressed %d repeated messages: %s\n",
					skipped,
					key,
				)

				if _, err := io.WriteString(w.output, summary); err != nil {
					return 0, err
				}
			}

			w.last[key] = now
			w.skipped[key] = 0
		}

		if _, err := io.WriteString(w.output, line); err != nil {
			return 0, err
		}
	}

	return len(p), nil
}

// raftLogKey groups selected connection errors by category and peer.
func raftLogKey(line string) (string, bool) {
	lower := strings.ToLower(line)

	var category string

	switch {
	case strings.Contains(lower, "failed to heartbeat"):
		category = "heartbeat"

	case strings.Contains(lower, "heartbeat timeout"):
		category = "heartbeat_timeout"

	case strings.Contains(lower, "failed to appendentries"):
		category = "append_entries"

	case strings.Contains(lower, "failed to append entries"):
		category = "append_entries"

	case strings.Contains(lower, "failed to contact"):
		category = "peer_connection"

	case strings.Contains(lower, "failed to connect to peer"):
		category = "peer_connection"

	default:
		return "", false
	}

	peer := "unknown"

	if match := raftPeerPattern.FindStringSubmatch(line); len(match) == 2 {
		peer = match[1]
	}

	return category + ":" + peer, true
}
