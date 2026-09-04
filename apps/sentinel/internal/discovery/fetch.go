package discovery

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"

	"github.com/skylogsio/skylogs/apps/sentinel/internal/security"
)

// FetchClusterPeers performs a signed GET to the main sentinel's cluster peers endpoint.
func FetchClusterPeers(ctx context.Context, baseURL, path, secret, requesterSentinelID string, timeout time.Duration) ([]Peer, error) {
	baseURL = strings.TrimRight(strings.TrimSpace(baseURL), "/")
	if baseURL == "" {
		return nil, fmt.Errorf("empty main sentinel base URL")
	}
	if path == "" {
		path = ClusterPeersPath
	}
	if !strings.HasPrefix(path, "/") {
		path = "/" + path
	}

	u, err := url.Parse(baseURL + path)
	if err != nil {
		return nil, fmt.Errorf("parse URL: %w", err)
	}

	ts := strconv.FormatInt(time.Now().Unix(), 10)
	msg := fmt.Sprintf("GET|%s|%s", path, ts)
	sig := security.Sign(secret, msg)

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, u.String(), nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("X-SkyLogs-Timestamp", ts)
	req.Header.Set("X-SkyLogs-Signature", sig)
	if requesterSentinelID != "" {
		req.Header.Set("X-SkyLogs-Sentinel-Id", requesterSentinelID)
	}

	client := &http.Client{Timeout: timeout}
	resp, err := client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, err
	}
	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("cluster fetch: %s: %s", resp.Status, string(body))
	}

	var out ClusterPeersResponse
	if err := json.Unmarshal(body, &out); err != nil {
		return nil, fmt.Errorf("decode cluster response: %w", err)
	}
	return out.Peers, nil
}
