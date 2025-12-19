package main

import (
	"context"
	"log"
	"net/http"
	"os"
	"time"

	"skylogs-sentinel/internal/alert"
	"skylogs-sentinel/internal/config"
	"skylogs-sentinel/internal/heartbeat"
	"skylogs-sentinel/internal/server"
)

func main() {
	cfg, err := config.Load("config.yaml")
	if err != nil {
		log.Fatal(err)
	}

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	state := heartbeat.NewState()

	receiver := &heartbeat.Receiver{State: state}

	sender := &heartbeat.Sender{
		PeerURL: cfg.Peer.URL,
		DC:      cfg.DC,
		Node:    hostname(),
		Client:  &http.Client{Timeout: 2 * time.Second},
	}

	go sender.Run(ctx, cfg.Heartbeat.Interval)

	go func() {
		ticker := time.NewTicker(cfg.Heartbeat.Interval)
		defer ticker.Stop()

		downSince := time.Time{}

		for range ticker.C {
			if !state.IsAlive(cfg.Heartbeat.Timeout) {
				if downSince.IsZero() {
					downSince = time.Now()
					alert.Send(cfg.Alert.Webhook, alert.Payload{
						Type:     "dc_peer_down",
						Peer:     cfg.Peer.Name,
						Since:    downSince,
						Severity: "critical",
					})
				}
			} else {
				downSince = time.Time{}
			}
		}
	}()

	httpServer := &http.Server{
		Addr:    cfg.Server.Listen,
		Handler: server.New(receiver, state),
	}

	log.Fatal(httpServer.ListenAndServe())
}

func hostname() string {
	h, _ := os.Hostname()
	return h
}

