package config

import (
	"testing"
)

func TestConfig_IsMainRole_andAgentPull(t *testing.T) {
	t.Parallel()
	cases := []struct {
		name       string
		role       string
		mainURL    string
		wantMain   bool
		wantAgent  bool
	}{
		{"default empty role is main", "", "", true, false},
		{"main explicit", "main", "http://x", true, false},
		{"primary explicit", "primary", "http://x", true, false},
		{"MAIN case", "MAIN", "", true, false},
		{"agent with main url pulls", "agent", "http://main:9191", false, true},
		{"secondary with main url pulls", "secondary", "http://main:9191", false, true},
		{"agent without main url", "agent", "", false, false},
		{"edge role not main", "edge", "", false, false},
		{"edge with main url still pulls", "edge", "http://m", false, true},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()
			var c Config
			c.Sentinel.Role = tc.role
			c.MainSentinel.BaseURL = tc.mainURL
			if g := c.IsMainRole(); g != tc.wantMain {
				t.Fatalf("IsMainRole: got %v want %v", g, tc.wantMain)
			}
			if g := c.AgentPullEnabled(); g != tc.wantAgent {
				t.Fatalf("AgentPullEnabled: got %v want %v", g, tc.wantAgent)
			}
		})
	}
}
