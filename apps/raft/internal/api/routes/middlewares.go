package routes

import (
	"time"

	"github.com/gin-gonic/gin"
	"github.com/rs/zerolog/log"
)

// We can have more middlewares here
// Wrote this one but not used . stayed here as a sample

func LoggerMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {

		// API Which we don't want to log them
		if c.Request.URL.Path == "/health" ||
			c.Request.URL.Path == "/leader" {

			c.Next()
			return
		}

		// Log for other APIs
		start := time.Now()
		c.Next()
		latency := time.Since(start)
		log.Info().
			Str("method", c.Request.Method).
			Str("path", c.Request.URL.Path).
			Int("status", c.Writer.Status()).
			Dur("latency", latency).
			Msg("http request")
	}
}
