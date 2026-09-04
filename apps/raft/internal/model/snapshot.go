package model

import (
	"encoding/json"

	"github.com/hashicorp/raft"
)

// fsmSnapshot implements raft.FSMSnapshot
type fsmSnapshot struct {
	data map[string]string
}

// Persist writes the snapshot to the given sink
func (s *fsmSnapshot) Persist(sink raft.SnapshotSink) error {
	err := func() error {
		b, err := json.Marshal(s.data)
		if err != nil {
			return err
		}

		if _, err := sink.Write(b); err != nil {
			return err
		}

		return sink.Close()
	}()

	if err != nil {
		sink.Cancel()
	}

	return err
}

// Release is called when we are finished with the snapshot
func (s *fsmSnapshot) Release() {}
