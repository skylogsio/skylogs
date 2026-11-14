export interface IRaftPeer {
  id: string;
  address?: string;
  known_leader_address?: string;
  suffrage: string;
  tcp_reachable: boolean;
}

export interface IRaftCluster {
  reporter_id: string;
  timestamp: string;
  local_state: string;
  known_leader_id: string;
  known_leader_address: string;
  peers: IRaftPeer[];
}

export interface IHaStatusResponse {
  enabled: boolean;
  cluster: IRaftCluster | null;
}
