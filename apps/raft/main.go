package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/hashicorp/raft"
	"github.com/rs/zerolog/log"
)

func main() {
	config := ParseConfig()

	// Existing data means this node is already a cluster member; resume from disk.
	// Do not re-bootstrap or re-POST /join (that requires the current leader and
	// fails hard during failover when VIP points at a follower or at this node).
	if hasExistingData(config.DataDir) {
		log.Info().
			Str("data_dir", config.DataDir).
			Msg("existing raft data found, ignoring bootstrap and join flags")

		config.Bootstrap = false
		config.JoinAddress = ""
	}

	// Create and start node
	node, err := NewNode(config)
	if err != nil {
		log.Fatal().Err(err).Msg("failed to create node")
	}

	// Start HTTP server
	httpServer := NewHTTPServer(node, config)
	go func() {
		if err := httpServer.Start(); err != nil {
			log.Fatal().Err(err).Msg("HTTP server failed")
		}
	}()

	// If join address is provided, join the cluster
	if config.JoinAddress != "" && !config.Bootstrap {
		time.Sleep(2 * time.Second) // Wait for leader to be ready

		raftAddr := fmt.Sprintf("%s:%d", config.AdvertiseAddress, config.RaftPort)
		fmt.Println("\n\nmain join address : ", raftAddr, "\n\n")
		joinReq := JoinRequest{
			NodeID:      config.NodeID,
			RaftAddress: raftAddr,
		}

		reqBody, err := json.Marshal(joinReq)
		if err != nil {
			log.Fatal().Err(err).Msg("failed to marshal join request")
		}

		joinURL := fmt.Sprintf("%s/join", config.JoinAddress)
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

	log.Info().
		Str("node_id", config.NodeID).
		Int("raft_port", config.RaftPort).
		Int("http_port", config.HTTPPort).
		Msg("node started")

	// Wait for shutdown signal
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, os.Interrupt, syscall.SIGTERM)
	<-sigCh
	//removing server from cluster to have
	f := node.raft.RemoveServer(
		raft.ServerID(config.NodeID),
		0,
		0,
	)
	err = f.Error()
	log.Info().Msg("shutting down")
	if err := node.Shutdown(); err != nil {
		log.Error().Err(err).Msg("failed to shutdown cleanly")
	}
	log.Info().Msg(time.Now().Format("2006-01-02T15:04:05.000"))
}

// maybe not a good place for this one
func hasExistingData(dir string) bool {
	entries, err := os.ReadDir(dir)
	if err != nil {
		return false
	}

	return len(entries) > 0
}
