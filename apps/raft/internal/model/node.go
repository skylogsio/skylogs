package model

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
	Config    *Config
	raft      *raft.Raft
	FSM       *FSM
	Logger    hclog.Logger
	transport *raft.NetworkTransport
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

func (n *Node) State() string {
	return n.raft.State().String()
}

func (n *Node) NodeID() string {
	return n.Config.NodeID
}

func (n *Node) RemoveServer() raft.IndexFuture {
	return n.raft.RemoveServer(raft.ServerID(n.NodeID()), 0, 0)
}

func (n *Node) SetupRaft() error {
	// Create data directory
	if err := os.MkdirAll(n.Config.DataDir, 0755); err != nil {
		return fmt.Errorf("failed to create data directory: %w", err)
	}

	// Setup Raft configuration
	raftConfig := raft.DefaultConfig()
	raftConfig.LocalID = raft.ServerID(n.Config.NodeID)
	raftConfig.Logger = n.Logger

	// Tune timeouts for faster leader election in testing
	raftConfig.HeartbeatTimeout = 1000 * time.Millisecond
	raftConfig.ElectionTimeout = 1000 * time.Millisecond
	raftConfig.CommitTimeout = 500 * time.Millisecond
	raftConfig.LeaderLeaseTimeout = 500 * time.Millisecond

	// Setup TCP transport
	listenAddr := fmt.Sprintf(
		"%s:%d",
		n.Config.BindAddress,
		n.Config.RaftPort,
	)

	advertiseAddr := fmt.Sprintf(
		"%s:%d",
		n.Config.AdvertiseAddress,
		n.Config.RaftPort,
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
	logStore, err := raftboltdb.NewBoltStore(filepath.Join(n.Config.DataDir, "raft-log.db"))
	if err != nil {
		return fmt.Errorf("failed to create log store: %w", err)
	}

	// Create stable store (BoltDB)
	stableStore, err := raftboltdb.NewBoltStore(filepath.Join(n.Config.DataDir, "raft-stable.db"))
	if err != nil {
		return fmt.Errorf("failed to create stable store: %w", err)
	}

	// Create snapshot store
	snapshotStore, err := raft.NewFileSnapshotStore(n.Config.DataDir, 2, os.Stdout)
	if err != nil {
		return fmt.Errorf("failed to create snapshot store: %w", err)
	}

	// Create Raft instance
	r, err := raft.NewRaft(raftConfig, n.FSM, logStore, stableStore, snapshotStore, transport)
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
					n.Config.NodeID,
					time.Now().Format("2006-01-02 15:04:05.000"),
				)
			}
		}
	}()

	// Bootstrap cluster if requested
	if n.Config.Bootstrap {
		configuration := raft.Configuration{
			Servers: []raft.Server{
				{
					ID:      raft.ServerID(n.Config.NodeID),
					Address: transport.LocalAddr(),
				},
			},
		}
		f := r.BootstrapCluster(configuration)
		if err := f.Error(); err != nil {
			return fmt.Errorf("failed to bootstrap cluster: %w", err)
		}
		n.Logger.Info("cluster bootstrapped", "node_id", n.Config.NodeID)
	}

	return nil
}

// Join adds a new node to the cluster
func (n *Node) Join(nodeID, addr string) error {
	if !n.IsLeader() {
		return fmt.Errorf("not the leader")
	}

	n.Logger.Info("received join request", "node_id", nodeID, "addr", addr)

	f := n.raft.AddVoter(raft.ServerID(nodeID), raft.ServerAddress(addr), 0, 0)
	if err := f.Error(); err != nil {
		return err
	}

	n.Logger.Info("node joined successfully", "node_id", nodeID)
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
