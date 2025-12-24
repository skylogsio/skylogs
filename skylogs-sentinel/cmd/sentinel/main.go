package main

import (
	"context"
	"log"
	"net/http"
	"os"
	"os/signal"
	"sync"
	"syscall"
	"time"

	"github.com/skylogs/skylogs-sentinel/internal/config"
	"github.com/skylogs/skylogs-sentinel/internal/heartbeat"
)

func main() {
	// ------------------------------------------------
	// Load config
	// ------------------------------------------------
	cfg, err := config.Load("config.yaml")
	if err != nil {
		log.Fatalf("failed to load config: %v", err)
	}

	log.Printf("SkyLogs Sentinel starting (id=%s)", cfg.Sentinel.ID)

	// ------------------------------------------------
	// Runtime state
	// ------------------------------------------------
	state := heartbeat.NewState(cfg.Runtime.MinUptime)

	// ------------------------------------------------
	// Context & shutdown handling
	// ------------------------------------------------
	ctx, cancel := context.WithCancel(context.Background())
	wg := &sync.WaitGroup{}

	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)

	// ------------------------------------------------
	// Start HTTP server (port 9191)
	// ------------------------------------------------
	wg.Add(1)
	go func() {
		defer wg.Done()
		startHTTPServer(cfg, state)
	}()

	// ------------------------------------------------
	// Start heartbeat loop
	// ------------------------------------------------
	wg.Add(1)
	go func() {
		defer wg.Done()
		runHeartbeatLoop(ctx, cfg, state)
	}()

	// ------------------------------------------------
	// Wait for shutdown signal
	// ------------------------------------------------
	<-sigCh
	log.Println("shutdown signal received")

	cancel()
	wg.Wait()

	log.Println("SkyLogs Sentinel stopped cleanly")
}

// ==================================================
// Heartbeat loop
// ==================================================

func runHeartbeatLoop(
	ctx context.Context,
	cfg *config.Config,
	state *heartbeat.State,
) {
	ticker := time.NewTicker(cfg.Heartbeat.Interval)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			return

		case <-ticker.C:
			err := heartbeat.Ping(
				cfg.Heartbeat.TargetURL,
				cfg.Heartbeat.Timeout,
			)

			if err != nil {
				heartbeat.OnFailure(cfg, state)
				continue
			}

			heartbeat.OnSuccess(state)
		}
	}
}

// ==================================================
// HTTP server
// ==================================================

func startHTTPServer(
	cfg *config.Config,
	state *heartbeat.State,
) {
	mux := http.NewServeMux()

	mux.HandleFunc("/health", func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("ok\n"))
	})

	mux.HandleFunc("/state", func(w http.ResponseWriter, _ *http.Request) {
		s := state.Snapshot()
		w.Header().Set("Content-Type", "application/json")
		w.Write(s)
	})

	server := &http.Server{
		Addr:         ":9191",
		Handler:      mux,
		ReadTimeout:  5 * time.Second,
		WriteTimeout: 5 * time.Second,
	}

	log.Println("HTTP server listening on :9191")

	if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
		log.Fatalf("http server failed: %v", err)
	}
}
