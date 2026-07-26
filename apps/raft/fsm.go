package main

import (
	"encoding/json"
	"io"
	"sync"

	"github.com/hashicorp/raft"
)

// Command represents a command to be applied to the FSM
type Command struct {
	Op    string `json:"op"`
	Key   string `json:"key"`
	Value string `json:"value"`
}

// FSM is the finite state machine that manages the key-value store
type FSM struct {
	mu     sync.RWMutex
	data   map[string]string
	notify *Notifier
}

func NewFSM(notify *Notifier) *FSM {
	return &FSM{
		data:   make(map[string]string),
		notify: notify,
	}
}

// Apply applies a Raft log entry to the FSM
func (f *FSM) Apply(log *raft.Log) interface{} {
	var cmd Command
	if err := json.Unmarshal(log.Data, &cmd); err != nil {
		return err
	}

	f.mu.Lock()
	switch cmd.Op {
	case "set":
		f.data[cmd.Key] = cmd.Value
	case "delete":
		delete(f.data, cmd.Key)
	}
	f.mu.Unlock()

	switch cmd.Op {
	case "set":
		f.notify.Notify(cmd.Key, json.RawMessage(cmd.Value))
	case "delete":
		f.notify.Notify(cmd.Key, nil)
	}

	return nil
}

// Snapshot returns a snapshot of the FSM
func (f *FSM) Snapshot() (raft.FSMSnapshot, error) {
	f.mu.RLock()
	defer f.mu.RUnlock()

	// Create a copy of the data
	data := make(map[string]string)
	for k, v := range f.data {
		data[k] = v
	}

	return &fsmSnapshot{data: data}, nil
}

// Restore restores the FSM from a snapshot
func (f *FSM) Restore(rc io.ReadCloser) error {
	defer rc.Close()

	var data map[string]string
	if err := json.NewDecoder(rc).Decode(&data); err != nil {
		return err
	}

	f.mu.Lock()
	defer f.mu.Unlock()

	f.data = data
	return nil
}

// Get retrieves a value from the FSM
func (f *FSM) Get(key string) (string, bool) {
	f.mu.RLock()
	defer f.mu.RUnlock()

	val, ok := f.data[key]
	return val, ok
}

// GetAll returns all data
func (f *FSM) GetAll() map[string]string {
	f.mu.RLock()
	defer f.mu.RUnlock()

	result := make(map[string]string)
	for k, v := range f.data {
		result[k] = v
	}
	return result
}

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
