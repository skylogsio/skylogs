package heartbeat

import (
	"encoding/json"
	"net/http"

	"skylogs-sentinel/pkg/model"
)

type Receiver struct {
	State *State
}

func (r *Receiver) Handle(w http.ResponseWriter, req *http.Request) {
	var hb model.Heartbeat

	if err := json.NewDecoder(req.Body).Decode(&hb); err != nil {
		http.Error(w, "invalid payload", http.StatusBadRequest)
		return
	}

	r.State.Update(hb.Timestamp)
	w.WriteHeader(http.StatusNoContent)
}

