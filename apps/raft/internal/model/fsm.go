package model

import (
	"encoding/json"
	"io"
	"sync"

	"github.com/hashicorp/raft"
)

// Command represents a command to be applied to the FSM

// FSM is the finite state machine that manages the key-value store
type FSM struct {
	mu       sync.RWMutex
	data     map[string]string
	notifier *Notifier
}

func NewFSM(notifier *Notifier) *FSM {
	return &FSM{
		data:     make(map[string]string),
		notifier: notifier,
	}
}

func (f *FSM) Apply(log *raft.Log) interface{} {
	//here we can persist our data on another database
	//redisDB := db.NewRedis()
	var cmd Command
	if err := json.Unmarshal(log.Data, &cmd); err != nil {
		return err
	}

	f.mu.Lock()
	defer f.mu.Unlock()

	switch cmd.Op {
	case "set":
		f.data[cmd.Key] = cmd.Value
		f.notifier.Notify(cmd.Key, json.RawMessage(cmd.Value))
		//if err := redisDB.Save(cmd); err != nil {
		//	return err
		//}
	case "delete":
		delete(f.data, cmd.Key)
		f.notifier.Notify(cmd.Key, nil)
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
