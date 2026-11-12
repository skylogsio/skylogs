package app

import (
	"github.com/rs/zerolog/log"
)

func (application *App) Shutdown() error {

	log.Info().
		Msg("shutting down application")

	if err := application.Service.Shutdown(); err != nil {

		log.Error().
			Err(err).
			Msg("failed shutting down service")

		return err
	}

	log.Info().
		Msg("application stopped")

	return nil
}
