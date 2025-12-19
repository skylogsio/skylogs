package server

import (
	"net/http"

	"skylogs-sentinel/internal/heartbeat"
)

func New(receiver *heartbeat.Receiver, state *heartbeat.State) http.Handler {
	mux := http.NewServeMux()

	mux.HandleFunc("/heartbeat", receiver.Handle)

	mux.HandleFunc("/status", func(w http.ResponseWriter, r *http.Request) {
		if state.IsAlive(0) {
			w.Write([]byte("UP"))
		} else {
			w.Write([]byte("DOWN"))
		}
	})

	return mux
}

