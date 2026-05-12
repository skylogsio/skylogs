package discovery

import (
	"encoding/json"
	"fmt"
	"os"
	"time"
)

// CachedPeersFile is the on-disk format for agent fallback when the main sentinel is unreachable.
type CachedPeersFile struct {
	Peers     []Peer    `json:"peers"`
	FetchedAt time.Time `json:"fetched_at"`
}

func LoadPeersCache(path string) ([]Peer, error) {
	if path == "" {
		return nil, fmt.Errorf("empty cache path")
	}
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}
	var c CachedPeersFile
	if err := json.Unmarshal(data, &c); err != nil {
		return nil, err
	}
	return c.Peers, nil
}

func SavePeersCache(path string, peers []Peer) error {
	if path == "" {
		return fmt.Errorf("empty cache path")
	}
	c := CachedPeersFile{
		Peers:     append([]Peer(nil), peers...),
		FetchedAt: time.Now().UTC(),
	}
	data, err := json.MarshalIndent(c, "", "  ")
	if err != nil {
		return err
	}
	tmp := path + ".tmp"
	if err := os.WriteFile(tmp, data, 0o644); err != nil {
		return err
	}
	return os.Rename(tmp, path)
}
