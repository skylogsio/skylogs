package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"sync"
	"syscall"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/alert"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/config"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/heartbeat"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/server"
)

func main() {
	// ------------------------------------------------
	// Load config
	// ------------------------------------------------
	cfg, err := config.Load("config.yaml")
	if err != nil {
		log.Fatalf("failed to load config: %v", err)
	}

	// ------------------------------------------------
	// Shared state (POINTER)
	// ------------------------------------------------
	state := heartbeat.NewState()

	// ------------------------------------------------
	// HTTP server (receiver)
	// ------------------------------------------------
	mux := http.NewServeMux()
	mux.Handle("/heartbeat", heartbeat.Receiver(state, cfg.Security.SharedSecret))

	httpServer := server.New(cfg.Server.Listen, mux)
	httpServer.Start()
	// status endpoint
	mux.Handle("/status", heartbeat.StatusHandler(state, cfg.Sentinel.Id))
	// ------------------------------------------------
	// Heartbeat sender loop
	// ------------------------------------------------
	ctx, cancel := context.WithCancel(context.Background())
	wg := &sync.WaitGroup{}

	sender := heartbeat.NewSender(
		cfg.Heartbeat.TargetURL,
		state,
		cfg.Security.SharedSecret,
		cfg.Heartbeat.Timeout,
	)

	wg.Add(1)
	go func() {
		defer wg.Done()

		ticker := time.NewTicker(cfg.Heartbeat.Interval)
		defer ticker.Stop()
		for {
			select {
			case <-ticker.C:
				if err := sender.Send(ctx); err != nil {
					if state.TimeSinceLastSeen() > cfg.Heartbeat.FailAfter {
						log.Println("Main SkyLogs unreachable")
						if state.MarkUnhealthyIfNeeded() {
							log.Println("Sending Alert")

							payload := alert.WebhookPayload{
								Instance: cfg.Alert.Instance,
								Description: fmt.Sprintf(
									"No heartbeat received for more than %s",
									cfg.Heartbeat.FailAfter,
								),
							}

							if err := alert.SendWebhook(
								ctx,
								cfg.Alert.WebhookUrl,
								cfg.Alert.Token,
								payload,
							); err != nil {
								log.Println("failed to send webhook:", err)
							}
						}
						//state.MarkUnhealthy()
					}
				}
			case <-ctx.Done():
				return
			}
		}
	}()

	// ------------------------------------------------
	// Graceful shutdown
	// ------------------------------------------------
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)

	<-sigCh
	log.Println("shutdown signal received")

	cancel()
	wg.Wait()

	httpServer.Shutdown(context.Background())
	log.Println("SkyLogs Sentinel stopped cleanly")
}
