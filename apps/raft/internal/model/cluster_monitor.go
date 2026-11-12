package model

import (
    "bytes"
    "context"
    "encoding/json"
    "fmt"
    "net"
    "net/http"
    "sort"
    "strings"
    "time"
)

// ClusterPeerObservation describes a cluster member's TCP reachability.
type ClusterPeerObservation struct {
    ID        string `json:"id"`
    Address   string `json:"address"`
    Suffrage  string `json:"suffrage"`
    Reachable bool   `json:"tcp_reachable"`
}

// ClusterChange describes a cluster state transition.
type ClusterChange struct {
    NodeID   string `json:"node_id"`
    Type     string `json:"type"`
    Previous string `json:"previous"`
    Current  string `json:"current"`
}

// ClusterObservation describes one node's view of the Raft cluster.
type ClusterObservation struct {
    ReporterID string                   `json:"reporter_id"`
    Timestamp  time.Time                `json:"timestamp"`
    State      string                   `json:"local_state"`
    LeaderID   string                   `json:"known_leader_id"`
    LeaderAddr string                   `json:"known_leader_address"`
    Peers      []ClusterPeerObservation `json:"peers"`
    Changes    []ClusterChange          `json:"changes,omitempty"`
    Error      string                   `json:"membership_error,omitempty"`
}

// ClusterAlertPayload represents the external alert API request body.
type ClusterAlertPayload struct {
    Instance    string `json:"instance"`
    Description string `json:"description"`
}

// clusterPeerState contains the confirmed reachability of one peer.
type clusterPeerState struct {
    Reachable bool
}

// clusterPendingState tracks an unconfirmed reachability change.
type clusterPendingState struct {
    Reachable bool
    Count     int
}

// clusterAlertTracker keeps peer states, delivery states, and last known leader.
type clusterAlertTracker struct {
    initialized bool
    peers       map[string]clusterPeerState
    pending     map[string]clusterPendingState
    sent        map[string]string
    lastLeader  string
}

// newClusterAlertTracker creates an empty alert tracker.
func newClusterAlertTracker() *clusterAlertTracker {
    return &clusterAlertTracker{
        peers:   make(map[string]clusterPeerState),
        pending: make(map[string]clusterPendingState),
        sent:    make(map[string]string),
    }
}

// ClusterStatus collects current Raft membership and node reachability.
func (n *Node) ClusterStatus(ctx context.Context) ClusterObservation {
    report := ClusterObservation{
        ReporterID: n.NodeID(),
        Peers:      make([]ClusterPeerObservation, 0),
    }

    n.refreshClusterLeadership(&report)

    future := n.raft.GetConfiguration()
    if err := future.Error(); err != nil {
        report.Error = err.Error()
        return report
    }

    dialer := &net.Dialer{Timeout: 300 * time.Millisecond}

    for _, server := range future.Configuration().Servers {
        if err := ctx.Err(); err != nil {
            report.Error = err.Error()
            return report
        }

        reachable := string(server.ID) == n.NodeID()

        if !reachable {
            conn, err := dialer.DialContext(ctx, "tcp", string(server.Address))
            if err == nil {
                reachable = true
                _ = conn.Close()
            }
        }

        report.Peers = append(report.Peers, ClusterPeerObservation{
            ID:        string(server.ID),
            Address:   string(server.Address),
            Suffrage:  fmt.Sprint(server.Suffrage),
            Reachable: reachable,
        })
    }

    sort.Slice(report.Peers, func(i, j int) bool { return report.Peers[i].ID < report.Peers[j].ID })
    n.refreshClusterLeadership(&report)

    return report
}

// refreshClusterLeadership updates the reporting node's role and known leader.
func (n *Node) refreshClusterLeadership(report *ClusterObservation) {
    report.State = n.State()
    report.LeaderAddr, report.LeaderID = n.GetLeader()
    report.Timestamp = time.Now().UTC()
}

// observePeers confirms peer reachability changes after two matching checks.
func (t *clusterAlertTracker) observePeers(report ClusterObservation) {
    current := make(map[string]bool, len(report.Peers))
    for _, peer := range report.Peers {
        current[peer.ID] = peer.Reachable
    }

    if !t.initialized {
        for id, reachable := range current {
            t.peers[id] = clusterPeerState{Reachable: reachable}
        }
        t.initialized = true
        return
    }

    for id := range t.peers {
        if _, exists := current[id]; exists {
            continue
        }

        delete(t.peers, id)
        delete(t.pending, id)
        delete(t.sent, id)
    }

    for id, reachable := range current {
        previous, exists := t.peers[id]

        if !exists {
            t.peers[id] = clusterPeerState{Reachable: reachable}
            delete(t.pending, id)
            continue
        }

        if previous.Reachable == reachable {
            delete(t.pending, id)
            continue
        }

        pending := t.pending[id]

        if pending.Count == 0 || pending.Reachable != reachable {
            t.pending[id] = clusterPendingState{Reachable: reachable, Count: 1}
            continue
        }

        pending.Count++
        if pending.Count < 2 {
            t.pending[id] = pending
            continue
        }

        t.peers[id] = clusterPeerState{Reachable: reachable}
        delete(t.pending, id)
    }
}

// observeLeader records a change between two known leader IDs.
func (t *clusterAlertTracker) observeLeader(leaderID string) string {
    if leaderID == "" {
        return ""
    }

    previous := t.lastLeader
    t.lastLeader = leaderID

    if previous == "" || previous == leaderID {
        return ""
    }
    return fmt.Sprintf("%s -> %s", previous, leaderID)
}

// clusterQuorumEstimate estimates voter quorum using TCP reachability.
func clusterQuorumEstimate(report ClusterObservation) string {
    voters := 0
    reachable := 0

    for _, peer := range report.Peers {
        if peer.Suffrage != "Voter" {
            continue
        }
        voters++
        if peer.Reachable {
            reachable++
        }
    }

    if voters == 0 {
        return "UNKNOWN"
    }
    if reachable >= voters/2+1 {
        return "AVAILABLE"
    }
    return "LOST"
}

// clusterDescription formats useful operational context for an alert.
func clusterDescription(report ClusterObservation, nodeID, status, lastLeader, leaderChange string) string {
    reachable := 0
    for _, peer := range report.Peers {
        if peer.Reachable {
            reachable++
        }
    }

    lines := []string{
        fmt.Sprintf("Node: %s", nodeID),
        fmt.Sprintf("Status: %s", status),
        fmt.Sprintf("Reporter: %s (%s)", report.ReporterID, report.State),
        fmt.Sprintf("Cluster: %d/%d reachable", reachable, len(report.Peers)),
        fmt.Sprintf("Quorum Estimate: %s", clusterQuorumEstimate(report)),
    }

    switch {
    case leaderChange != "":
        lines = append(lines, "Leader: "+leaderChange)
    case report.LeaderID != "":
        lines = append(lines, "Leader: "+report.LeaderID)
    case lastLeader != "":
        lines = append(lines, "Leader: NONE", "Previous Leader: "+lastLeader)
    default:
        lines = append(lines, "Leader: NONE")
    }

    return strings.Join(lines, "\n")
}

// canSendClusterAlerts allows leaders and candidates past their grace period.
func (n *Node) canSendClusterAlerts(candidateSince time.Time, grace time.Duration) bool {
    switch n.State() {
    case "Leader":
        return true
    case "Candidate":
        return !candidateSince.IsZero() && time.Since(candidateSince) >= grace
    default:
        return false
    }
}

// watchClusterChanges observes peers and assigns alert sending to eligible nodes.
func (n *Node) watchClusterChanges(ctx context.Context) {
    interval := n.Config.ClusterCheckInterval
    if interval <= 0 {
        interval = 5 * time.Second
    }

    grace := n.Config.ClusterCandidateGrace
    if grace <= 0 {
        grace = 5 * time.Second
    }

    ticker := time.NewTicker(interval)
    defer ticker.Stop()

    tracker := newClusterAlertTracker()
    client := &http.Client{Timeout: 3 * time.Second}

    var candidateSince time.Time
    var senderRole string

    for {
        select {
        case <-ctx.Done():
            return

        case <-ticker.C:
            if ctx.Err() != nil {
                return
            }

            role := n.State()

            if role == "Candidate" {
                if candidateSince.IsZero() {
                    candidateSince = time.Now()
                }
            } else {
                candidateSince = time.Time{}
            }

            checkCtx, cancel := context.WithTimeout(ctx, 4*time.Second)
            report := n.ClusterStatus(checkCtx)
            checkErr := checkCtx.Err()
            cancel()

            if ctx.Err() != nil {
                return
            }
            if checkErr != nil || report.Error != "" {
                continue
            }

            tracker.observePeers(report)
            leaderChange := tracker.observeLeader(report.LeaderID)

            if !n.canSendClusterAlerts(candidateSince, grace) {
                senderRole = ""
                continue
            }

            // Reconcile alerts whenever this node becomes an eligible sender.
            if senderRole != report.State {
                tracker.sent = make(map[string]string)
                senderRole = report.State
            }

            n.dispatchClusterAlerts(ctx, client, tracker, report, leaderChange, candidateSince, grace)
        }
    }
}

// dispatchClusterAlerts resolves unknown instances before applying current states.
func (n *Node) dispatchClusterAlerts(ctx context.Context, client *http.Client, tracker *clusterAlertTracker, report ClusterObservation, leaderChange string, candidateSince time.Time, grace time.Duration) {
    ids := make([]string, 0, len(tracker.peers))
    for id := range tracker.peers {
        ids = append(ids, id)
    }
    sort.Strings(ids)

    // Phase 1: Resolve each instance before firing any current alerts.
    for _, id := range ids {
        if ctx.Err() != nil {
            return
        }
        if !n.canSendClusterAlerts(candidateSince, grace) {
            return
        }
        if tracker.sent[id] != "" {
            continue
        }

        payload := ClusterAlertPayload{
            Instance:    id,
            Description: clusterDescription(report, id, "ALERT RESET", tracker.lastLeader, leaderChange),
        }

        if err := n.sendClusterAlert(ctx, client, n.Config.ClusterAlertResolveURL, payload); err != nil {
            if ctx.Err() == nil {
                n.Logger.Warn("cluster alert resolve failed", "instance", id, "error", err)
            }
            continue
        }

        tracker.sent[id] = "RESOLVE"
    }

    // Never Fire before initial reconciliation has completed.
    for _, id := range ids {
        if tracker.sent[id] == "" {
            return
        }
    }

    // Phase 2: Apply the latest confirmed peer states.
    for _, id := range ids {
        if ctx.Err() != nil {
            return
        }
        if !n.canSendClusterAlerts(candidateSince, grace) {
            return
        }

        peer := tracker.peers[id]
        state := "FIRE"
        if peer.Reachable {
            state = "RESOLVE"
        }

        if id == n.NodeID() && state == "FIRE" {
            continue
        }
        if tracker.sent[id] == state {
            continue
        }

        url := n.Config.ClusterAlertFireURL
        status := "UNREACHABLE"

        if state == "RESOLVE" {
            url = n.Config.ClusterAlertResolveURL
            status = "RECOVERED"
        }

        payload := ClusterAlertPayload{
            Instance:    id,
            Description: clusterDescription(report, id, status, tracker.lastLeader, leaderChange),
        }

        // Record Fire before sending to avoid duplicate alert creation.
        if state == "FIRE" {
            tracker.sent[id] = state
        }

        err := n.sendClusterAlert(ctx, client, url, payload)
        if err != nil {
            if ctx.Err() == nil {
                n.Logger.Warn("cluster alert delivery failed", "instance", id, "error", err)
            }
            continue
        }

        tracker.sent[id] = state
    }
}

// sendClusterAlert makes one cancellable HTTP request.
func (n *Node) sendClusterAlert(ctx context.Context, client *http.Client, url string, payload ClusterAlertPayload) error {
    if ctx.Err() != nil {
        return ctx.Err()
    }

    sendCtx, cancel := context.WithTimeout(ctx, 3*time.Second)
    defer cancel()

    return n.postClusterAlert(sendCtx, client, url, payload)
}

// postClusterAlert sends an alert payload with configured HTTP headers.
func (n *Node) postClusterAlert(ctx context.Context, client *http.Client, url string, payload ClusterAlertPayload) error {
    if ctx.Err() != nil {
        return ctx.Err()
    }

    body, err := json.Marshal(payload)
    if err != nil {
        return fmt.Errorf("marshal cluster alert: %w", err)
    }

    req, err := http.NewRequestWithContext(ctx, http.MethodPost, url, bytes.NewReader(body))
    if err != nil {
        return fmt.Errorf("create cluster alert request: %w", err)
    }

    req.Header.Set("Content-Type", "application/json")

    for name, values := range n.Config.ClusterReportHeaders {
        for _, value := range values {
            req.Header.Add(name, value)
        }
    }

    resp, err := client.Do(req)
    if err != nil {
        return fmt.Errorf("send cluster alert: %w", err)
    }
    defer resp.Body.Close()

    if resp.StatusCode < 200 || resp.StatusCode >= 300 {
        return fmt.Errorf("cluster alert HTTP status %d", resp.StatusCode)
    }

    return nil
}
