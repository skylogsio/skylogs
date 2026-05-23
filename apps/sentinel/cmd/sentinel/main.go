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

	"github.com/skylogsio/skylogs/apps/sentinel/internal/alert"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/cluster"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/config"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/discovery"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/heartbeat"
	"github.com/skylogsio/skylogs/apps/sentinel/internal/server"
)

func main() {
	cfg, err := config.Load("config.yaml")
	if err != nil {
		log.Fatalf("failed to load config: %v", err)
	}
	if cfg.Sentinel.ID == "" {
		log.Fatal("sentinel.id (or SENTINEL_ID) is required")
	}
	if cfg.IsMainRole() && cfg.MainSentinel.BaseURL != "" {
		log.Println("notice: main_sentinel.base_url is ignored when sentinel.role is main/primary/default")
	}
	if cfg.AgentPullEnabled() && cfg.MainSentinel.RefreshInterval <= 0 {
		cfg.MainSentinel.RefreshInterval = 30 * time.Second
	}

	reg := heartbeat.NewRegistry()
	pl := discovery.NewPeerList()

	sendAlert := func(ctx context.Context, instance, description string) error {
		return alert.SendWebhook(ctx, cfg.Alert.WebhookUrl, cfg.Alert.Token, alert.WebhookPayload{
			Instance:    instance,
			Description: description,
		})
	}

	wc := heartbeat.WatcherConfig{
		Interval:  cfg.Heartbeat.Interval,
		Timeout:   cfg.Heartbeat.Timeout,
		FailAfter: cfg.Heartbeat.FailAfter,
		Secret:    cfg.Security.SharedSecret,
		SelfID:    cfg.Sentinel.ID,
	}

	sup := heartbeat.NewSupervisor(reg, wc, sendAlert)

	ctx, cancel := context.WithCancel(context.Background())
	wg := &sync.WaitGroup{}

	wg.Add(1)
	go func() {
		defer wg.Done()
		for {
			select {
			case <-ctx.Done():
				return
			case peers, ok := <-pl.Updates():
				if !ok {
					return
				}
				sup.Reconcile(ctx, peersForAgentWatch(cfg, peers))
			}
		}
	}()

	rawPeers, bootstrap := bootstrapPeerList(cfg)
	filtered := discovery.FilterPeers(rawPeers, cfg.Sentinel.SelfInstanceName, cfg.Sentinel.ID)
	if len(filtered) == 0 && !cfg.AgentPullEnabled() {
		log.Println("warning: no peers configured (add peers: in config.yaml or enable agent sync); only inbound heartbeats will update status")
	}
	if len(filtered) == 0 && cfg.AgentPullEnabled() {
		log.Println("warning: no peers yet after bootstrap; inbound heartbeats only until main is reachable")
	}
	if bootstrap != "" {
		log.Println("bootstrap:", bootstrap)
	}
	pl.Update(filtered)

	if cfg.Server.Listen == "" {
		cfg.Server.Listen = ":9191"
	}
	if cfg.Heartbeat.FailAfter <= 0 {
		cfg.Heartbeat.FailAfter = 15 * time.Second
	}
	stale := cfg.Heartbeat.FailAfter
	if cfg.Security.AllowedDrift <= 0 {
		cfg.Security.AllowedDrift = 10 * time.Second
	}

	mux := http.NewServeMux()
	mux.Handle("/heartbeat", heartbeat.Receiver(reg, cfg.Security.SharedSecret, cfg.Security.AllowedDrift))

	if cfg.IsMainRole() {
		mux.Handle(discovery.ClusterPeersPath,
			cluster.PeersHandler(cfg.Security.SharedSecret, cfg.Security.AllowedDrift, func() []discovery.Peer {
				return snapshotPeers(cfg.Peers)
			}))
	}

	mux.Handle("/status", heartbeat.StatusHandlerJSON(reg, pl, cfg.Sentinel.ID, stale))

	if cfg.AgentPullEnabled() {
		wg.Add(1)
		go agentClusterSync(ctx, wg, cfg, pl)
	}

	httpServer := server.New(cfg.Server.Listen, mux)
	httpServer.Start()

	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)

	<-sigCh
	log.Println("shutdown signal received")

	cancel()
	pl.Close()
	sup.Shutdown()
	wg.Wait()

	httpServer.Shutdown(context.Background())
	log.Println("SkyLogs Sentinel stopped cleanly")
}

func peersForAgentWatch(cfg *config.Config, peers []discovery.Peer) []discovery.Peer {
	filtered := discovery.FilterPeers(peers, cfg.Sentinel.SelfInstanceName, cfg.Sentinel.ID)
	if !cfg.AgentPullEnabled() {
		return filtered
	}
	return discovery.AppendMainWatchPeer(
		filtered,
		cfg.MainSentinel.BaseURL,
		cfg.MainSentinel.Name,
		cfg.MainSentinel.SentinelID,
	)
}

func snapshotPeers(peers []discovery.Peer) []discovery.Peer {
	out := make([]discovery.Peer, len(peers))
	copy(out, peers)
	return out
}

func bootstrapPeerList(cfg *config.Config) (raw []discovery.Peer, label string) {
	if !cfg.AgentPullEnabled() {
		return cfg.Peers, "main/standalone from config.peers"
	}

	fetchCtxTimeout := cfg.Heartbeat.Timeout * 5
	if fetchCtxTimeout <= 0 {
		fetchCtxTimeout = 15 * time.Second
	}
	tctx, tcancel := context.WithTimeout(context.Background(), fetchCtxTimeout)
	peers, err := discovery.FetchClusterPeers(tctx,
		cfg.MainSentinel.BaseURL,
		cfg.MainSentinel.PeersPath,
		cfg.Security.SharedSecret,
		cfg.Sentinel.ID,
		fetchCtxTimeout,
	)
	tcancel()
	if err == nil {
		if cfg.MainSentinel.CacheFile != "" {
			if saveErr := discovery.SavePeersCache(cfg.MainSentinel.CacheFile, peers); saveErr != nil {
				log.Println("warn: persist peers cache:", saveErr)
			}
		}
		return peers, "agent: fetched from main sentinel"
	}

	if cfg.MainSentinel.CacheFile != "" {
		if cached, cerr := discovery.LoadPeersCache(cfg.MainSentinel.CacheFile); cerr == nil && len(cached) > 0 {
			log.Printf("agent: main unreachable (%v); using peer cache %q", err, cfg.MainSentinel.CacheFile)
			return cached, "agent: disk cache"
		}
	}
	if len(cfg.Peers) > 0 {
		log.Printf("agent: main unreachable (%v); using config.peers fallback", err)
		return cfg.Peers, "agent: config fallback"
	}
	log.Printf("agent: main unreachable (%v); no cache or config peers", err)
	return nil, "agent: empty peer list"
}

func agentClusterSync(ctx context.Context, wg *sync.WaitGroup, cfg *config.Config, pl *discovery.PeerList) {
	defer wg.Done()
	ticker := time.NewTicker(cfg.MainSentinel.RefreshInterval)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			timeout := cfg.MainSentinel.RefreshInterval
			if timeout <= 0 {
				timeout = 30 * time.Second
			}
			if timeout > 2*time.Minute {
				timeout = 2 * time.Minute
			}
			tctx, tcancel := context.WithTimeout(context.Background(), timeout)
			raw, err := discovery.FetchClusterPeers(tctx,
				cfg.MainSentinel.BaseURL,
				cfg.MainSentinel.PeersPath,
				cfg.Security.SharedSecret,
				cfg.Sentinel.ID,
				timeout,
			)
			tcancel()
			if err != nil {
				log.Printf("agent: cluster refresh failed: %v", err)
				continue
			}
			if cfg.MainSentinel.CacheFile != "" {
				if saveErr := discovery.SavePeersCache(cfg.MainSentinel.CacheFile, raw); saveErr != nil {
					log.Println("warn: persist peers cache:", saveErr)
				}
			}
			pl.Update(discovery.FilterPeers(raw, cfg.Sentinel.SelfInstanceName, cfg.Sentinel.ID))
		}
	}
}
