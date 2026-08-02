package service

import (
    . "RAFT_Service/internal/model"
    "bytes"
    "fmt"
    "net/http"
    "time"

    "encoding/json"
    "os"

    "github.com/gin-gonic/gin"
    "github.com/hashicorp/go-hclog"
    "github.com/hashicorp/raft"
    "github.com/rs/zerolog/log"
)

type JoinRequest struct {
    NodeID      string `json:"node_id"`
    RaftAddress string `json:"raft_address"`
}

func NewNode(config *Config) (*Node, error) {
    // Create logger for Raft (must use hclog)
    logger := hclog.New(&hclog.LoggerOptions{
        Name:   "raft",
        Level:  hclog.Info,
        Output: os.Stdout,
        // Color:  hclog.ColorOff, //for removing colors and readable log
    })
    // Create notifier
    notifier := NewNotifier(config.NotifyURL, config.NotifySecret, config.NotifyHeader, log.Logger)
    // Create FSM
    fsm := NewFSM(notifier)

    node := &Node{
        Config: config,
        FSM:    fsm,
        Logger: logger,
    }

    if err := node.SetupRaft(); err != nil {
        return nil, err
    }

    return node, nil
}

type NodeService struct {
    node *Node
}

func NewNodeService(node *Node) *NodeService {
    return &NodeService{
        node: node,
    }
}

func (ns *NodeService) Set(cmd Command) error {

    cmdBytes, err := json.Marshal(cmd)
    if err != nil {
        return err
    }

    return ns.node.Apply(cmdBytes)
}

func (ns *NodeService) Delete(cmd Command) error {
    cmdBytes, err := json.Marshal(cmd)
    if err != nil {
        return err
    }
    return ns.node.Apply(cmdBytes)
}

func (ns *NodeService) Get(key string) (string, bool) {
    return ns.node.FSM.Get(key)
}

func (ns *NodeService) GetAll() map[string]string {
    return ns.node.FSM.GetAll()
}

func (ns *NodeService) Join(req JoinRequest) error {
    return ns.node.Join(req.NodeID, req.RaftAddress)
}
func (ns *NodeService) Status() gin.H {
    leader_addr, _ := ns.node.GetLeader()
    return gin.H{
        "node_id":   ns.node.Config.NodeID,
        "is_leader": ns.node.IsLeader(),
        "leader":    leader_addr,
        "state":     ns.node.State(),
    }
}
func (ns *NodeService) Shutdown() error {
    return ns.node.Shutdown()
}

func (ns *NodeService) RemoveServer() raft.IndexFuture {
    return ns.node.RemoveServer()
}

func (ns *NodeService) Leader() (gin.H, int) {

    isLeader := ns.node.IsLeader()
    leaderAddr, leaderId := ns.node.GetLeader()

    statusCode := http.StatusServiceUnavailable
    if isLeader {
        statusCode = http.StatusOK
    }

    return gin.H{
        "leader":     isLeader,
        "leaderNode": leaderId,
        "address":    leaderAddr,
    }, statusCode
}

func (ns *NodeService) JoinClusterIfNeeded() {
    if ns.node.Config.JoinAddress != "" && !ns.node.Config.Bootstrap {
        time.Sleep(2 * time.Second) // Wait for leader to be ready

        raftAddr := fmt.Sprintf("%s:%d", ns.node.Config.AdvertiseAddress, ns.node.Config.RaftPort)
        fmt.Println("\n\nmain join address : ", raftAddr, "\n\n")
        joinReq := JoinRequest{
            NodeID:      ns.node.Config.NodeID,
            RaftAddress: raftAddr,
        }

        reqBody, err := json.Marshal(joinReq)
        if err != nil {
            log.Fatal().Err(err).Msg("failed to marshal join request")
        }

        joinURL := fmt.Sprintf("%s/join", ns.node.Config.JoinAddress)
        log.Info().Str("url", joinURL).Msg("attempting to join cluster")

        resp, err := http.Post(joinURL, "application/json", bytes.NewReader(reqBody))
        if err != nil {
            log.Fatal().Err(err).Msg("failed to join cluster")
        }
        defer resp.Body.Close()

        if resp.StatusCode != http.StatusOK {
            log.Fatal().Int("status", resp.StatusCode).Msg("join request failed")
        }

        log.Info().Msg("successfully joined cluster")
    }
}
