package app

import (
	"fmt"
	"os"
	"os/signal"
	"syscall"

	"github.com/rs/zerolog/log"
)

func (application *App) Run() error {
	// initialize address
	addr := fmt.Sprintf(
		"%s:%d",
		application.Config.BindAddress,
		application.Config.HTTPPort,
	)
	//starting the gin engine with proper routes
	go func() {
		log.Info().
			Str("address", addr).
			Msg("starting http server")

		if err := application.Router.Run(addr); err != nil {

			log.Error().
				Err(err).
				Msg("http server stopped")
		}
	}()
	// Join cluster
	application.Service.JoinClusterIfNeeded()
	//Log startup
	log.Info().
		Str("node_id", application.Config.NodeID).
		Int("raft_port", application.Config.RaftPort).
		Int("http_port", application.Config.HTTPPort).
		Str("notify_url", application.Config.NotifyURL).
		Msg("application started")

	// wait for shutdown
	signalChannel := make(chan os.Signal, 1)
	signal.Notify(
		signalChannel,
		os.Interrupt,
		syscall.SIGTERM,
	)
	<-signalChannel
	//Removing Server from cluster
	f := application.Service.RemoveServer()
	err := f.Error()
	if err != nil {
		return err
	}
	//Server Shutdown
	return application.Shutdown()
}
