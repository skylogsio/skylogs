package main

import (
	"fmt"
	"net"
	"os"
	"path/filepath"
	"time"

	"github.com/hashicorp/go-hclog"
	"github.com/hashicorp/raft"
	raftboltdb "github.com/hashicorp/raft-boltdb"
)

type Node struct {
	config    *Config
	raft      *raft.Raft
	fsm       *FSM
	logger    hclog.Logger
	transport *raft.NetworkTransport
}

func NewNode(config *Config, notify *Notifier) (*Node, error) {
	// Create logger for Raft (must use hclog)
	logger := hclog.New(&hclog.LoggerOptions{
		Name:   "raft",
		Level:  hclog.Info,
		Output: os.Stdout,
		// Color:  hclog.ColorOff, //for removing colors and readable log
	})

	// Create FSM
	fsm := NewFSM(notify)

	node := &Node{
		config: config,
		fsm:    fsm,
		logger: logger,
	}

	if err := node.setupRaft(); err != nil {
		return nil, err
	}

	return node, nil
}

func (n *Node) setupRaft() error {
	// Create data directory
	if err := os.MkdirAll(n.config.DataDir, 0755); err != nil {
		return fmt.Errorf("failed to create data directory: %w", err)
	}

	// Setup Raft configuration
	raftConfig := raft.DefaultConfig()
	raftConfig.LocalID = raft.ServerID(n.config.NodeID)
	raftConfig.Logger = n.logger

	// Tune timeouts for faster leader election in testing
	raftConfig.HeartbeatTimeout = 1000 * time.Millisecond
	raftConfig.ElectionTimeout = 1000 * time.Millisecond
	raftConfig.CommitTimeout = 500 * time.Millisecond
	raftConfig.LeaderLeaseTimeout = 500 * time.Millisecond

	// Setup TCP transport
	listenAddr := fmt.Sprintf(
		"%s:%d",
		n.config.BindAddress,
		n.config.RaftPort,
	)

	advertiseAddr := fmt.Sprintf(
		"%s:%d",
		n.config.AdvertiseAddress,
		n.config.RaftPort,
	)

	advertiseTCP, err := net.ResolveTCPAddr(
		"tcp",
		advertiseAddr,
	)
	if err != nil {
		return err
	}

	transport, err := raft.NewTCPTransport(
		listenAddr,
		advertiseTCP,
		3,
		10*time.Second,
		os.Stdout,
	)

	if err != nil {
		return fmt.Errorf("failed to create TCP transport: %w", err)
	}
	n.transport = transport

	// Create log store (BoltDB)
	logStore, err := raftboltdb.NewBoltStore(filepath.Join(n.config.DataDir, "raft-log.db"))
	if err != nil {
		return fmt.Errorf("failed to create log store: %w", err)
	}

	// Create stable store (BoltDB)
	stableStore, err := raftboltdb.NewBoltStore(filepath.Join(n.config.DataDir, "raft-stable.db"))
	if err != nil {
		return fmt.Errorf("failed to create stable store: %w", err)
	}

	// Create snapshot store
	snapshotStore, err := raft.NewFileSnapshotStore(n.config.DataDir, 2, os.Stdout)
	if err != nil {
		return fmt.Errorf("failed to create snapshot store: %w", err)
	}

	// Create Raft instance
	r, err := raft.NewRaft(raftConfig, n.fsm, logStore, stableStore, snapshotStore, transport)
	if err != nil {
		return fmt.Errorf("failed to create raft: %w", err)
	}
	n.raft = r

	// Time of new leader winning!
	go func() {
		for isLeader := range n.raft.LeaderCh() {
			if isLeader {
				fmt.Printf(
					"Node %s became leader at %s\n",
					n.config.NodeID,
					time.Now().Format("2006-01-02 15:04:05.000"),
				)
			}
		}
	}()

	// Bootstrap cluster if requested
	if n.config.Bootstrap {
		configuration := raft.Configuration{
			Servers: []raft.Server{
				{
					ID:      raft.ServerID(n.config.NodeID),
					Address: transport.LocalAddr(),
				},
			},
		}
		f := r.BootstrapCluster(configuration)
		if err := f.Error(); err != nil {
			return fmt.Errorf("failed to bootstrap cluster: %w", err)
		}
		n.logger.Info("cluster bootstrapped", "node_id", n.config.NodeID)
	}

	return nil
}

// IsLeader checks if this node is the leader
func (n *Node) IsLeader() bool {
	return n.raft.State() == raft.Leader
}

// GetLeader returns the current leader address
func (n *Node) GetLeader() string {
	addr, _ := n.raft.LeaderWithID()
	return string(addr)
}

// Apply applies a command to the Raft cluster
func (n *Node) Apply(cmd []byte) error {
	if !n.IsLeader() {
		return fmt.Errorf("not the leader")
	}

	f := n.raft.Apply(cmd, 10*time.Second)
	if err := f.Error(); err != nil {
		return err
	}

	return nil
}

// Join adds a new node to the cluster
func (n *Node) Join(nodeID, addr string) error {
	if !n.IsLeader() {
		return fmt.Errorf("not the leader")
	}

	n.logger.Info("received join request", "node_id", nodeID, "addr", addr)

	f := n.raft.AddVoter(raft.ServerID(nodeID), raft.ServerAddress(addr), 0, 0)
	if err := f.Error(); err != nil {
		return err
	}

	n.logger.Info("node joined successfully", "node_id", nodeID)
	return nil
}

// Shutdown shuts down the Raft node
func (n *Node) Shutdown() error {
	if n.raft != nil {
		f := n.raft.Shutdown()
		if err := f.Error(); err != nil {
			return err
		}
	}
	return nil
}
