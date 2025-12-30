package heartbeat

import (
	"net/http"
)

func Receiver(state *State) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		state.MarkSeen()
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("ok"))
	}
}
