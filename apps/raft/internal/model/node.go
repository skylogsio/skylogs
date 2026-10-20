package model

import (
    "context"
    "fmt"
    "net"
    "os"
    "path/filepath"
    "time"

    "github.com/hashicorp/go-hclog"
    "github.com/hashicorp/raft"
    raftboltdb "github.com/hashicorp/raft-boltdb"
)

// Node represents a local Raft instance and its dependencies.
type Node struct {
    Config        *Config
    raft          *raft.Raft
    FSM           *FSM
    Logger        hclog.Logger
    transport     *raft.NetworkTransport
    monitorCancel context.CancelFunc
}

// IsLeader checks whether this node is the current Raft leader.
func (n *Node) IsLeader() bool {
    return n.raft.State() == raft.Leader
}

// GetLeader returns the currently known leader address and ID.
func (n *Node) GetLeader() (string, string) {
    addr, nodeID := n.raft.LeaderWithID()
    return string(addr), string(nodeID)
}

// State returns the current local Raft state.
func (n *Node) State() string {
    return n.raft.State().String()
}

// NodeID returns the configured node identifier.
func (n *Node) NodeID() string {
    return n.Config.NodeID
}

// RemoveServer removes the local node from Raft membership when called on the leader.
func (n *Node) RemoveServer() raft.IndexFuture {
    return n.raft.RemoveServer(raft.ServerID(n.NodeID()), 0, 0)
}

// SetupRaft initializes Raft, storage, transport, and cluster alert monitoring.
func (n *Node) SetupRaft() error {
    // Create data directory.
    if err := os.MkdirAll(n.Config.DataDir, 0755); err != nil {
        return fmt.Errorf("failed to create data directory: %w", err)
    }

    // Configure Raft and its filtered logger.
    raftConfig := raft.DefaultConfig()
    raftConfig.LocalID = raft.ServerID(n.Config.NodeID)

    filteredOutput := NewRaftLogFilter(os.Stdout, n.Config.RaftLogInterval)

    raftConfig.Logger = hclog.New(&hclog.LoggerOptions{
        Name:   "raft",
        Level:  n.Logger.GetLevel(),
        Output: filteredOutput,
    })

    // Preserve existing Raft timing settings.
    raftConfig.HeartbeatTimeout = 1000 * time.Millisecond
    raftConfig.ElectionTimeout = 1000 * time.Millisecond
    raftConfig.CommitTimeout = 500 * time.Millisecond
    raftConfig.LeaderLeaseTimeout = 500 * time.Millisecond

    // Configure TCP transport.
    listenAddr := fmt.Sprintf("%s:%d", n.Config.BindAddress, n.Config.RaftPort)
    advertiseAddr := fmt.Sprintf("%s:%d", n.Config.AdvertiseAddress, n.Config.RaftPort)

    advertiseTCP, err := net.ResolveTCPAddr("tcp", advertiseAddr)
    if err != nil {
        return fmt.Errorf("resolve Raft address: %w", err)
    }

    transport, err := raft.NewTCPTransport(listenAddr, advertiseTCP, 3, 10*time.Second, filteredOutput)
    if err != nil {
        return fmt.Errorf("create Raft transport: %w", err)
    }

    n.transport = transport

    // Initialize persistent Raft stores.
    logStore, err := raftboltdb.NewBoltStore(filepath.Join(n.Config.DataDir, "raft-log.db"))
    if err != nil {
        return fmt.Errorf("create Raft log store: %w", err)
    }

    stableStore, err := raftboltdb.NewBoltStore(filepath.Join(n.Config.DataDir, "raft-stable.db"))
    if err != nil {
        return fmt.Errorf("create Raft stable store: %w", err)
    }

    snapshotStore, err := raft.NewFileSnapshotStore(n.Config.DataDir, 2, os.Stdout)
    if err != nil {
        return fmt.Errorf("create Raft snapshot store: %w", err)
    }

    // Initialize Raft.
    r, err := raft.NewRaft(raftConfig, n.FSM, logStore, stableStore, snapshotStore, transport)
    if err != nil {
        return fmt.Errorf("initialize Raft: %w", err)
    }

    n.raft = r

    // Observe local leadership changes.
    go func() {
        for isLeader := range n.raft.LeaderCh() {
            if isLeader {
                n.Logger.Info("node became leader", "node_id", n.Config.NodeID, "time", time.Now().Format("2006-01-02 15:04:05.000"))
            }
        }
    }()

    // Bootstrap a new cluster when requested.
    if n.Config.Bootstrap {
        configuration := raft.Configuration{
            Servers: []raft.Server{
                {
                    ID:      raft.ServerID(n.Config.NodeID),
                    Address: transport.LocalAddr(),
                },
            },
        }

        if err := r.BootstrapCluster(configuration).Error(); err != nil {
            return fmt.Errorf("bootstrap Raft cluster: %w", err)
        }

        n.Logger.Info("cluster bootstrapped", "node_id", n.Config.NodeID)
    }

    // Start alert monitoring when both external endpoints are configured.
    if n.Config.ClusterAlertFireURL != "" && n.Config.ClusterAlertResolveURL != "" {
        ctx, cancel := context.WithCancel(context.Background())
        n.monitorCancel = cancel
        go n.watchClusterChanges(ctx)
    }

    return nil
}

// Join adds a voting node to the Raft cluster.
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

// Shutdown stops cluster monitoring and the local Raft instance.
func (n *Node) Shutdown() error {
    if n.monitorCancel != nil {
        n.monitorCancel()
    }

    if n.raft != nil {
        if err := n.raft.Shutdown().Error(); err != nil {
            return err
        }
    }

    return nil
}

// Apply submits a command to the Raft cluster.
func (n *Node) Apply(cmd []byte) error {
    if !n.IsLeader() {
        return fmt.Errorf("not the leader")
    }

    if err := n.raft.Apply(cmd, 10*time.Second).Error(); err != nil {
        return err
    }
    return nil
}
