"use client";

import { useMemo, useState } from "react";

import { Box, Button, Tooltip, Typography } from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import { getHaStatus } from "@/api/ha";
import { createPulseAnimation } from "@/utils/animation";
import RaftClusterModal from "./RaftClusterModal";

const pulseGreen = createPulseAnimation("var(--mui-palette-success-main)");
const pulseOrange = createPulseAnimation("var(--mui-palette-warning-main)");

export default function TopBarHaIndicator() {
  const [isModalOpen, setIsModalOpen] = useState(false);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["ha-status"],
    queryFn: () => getHaStatus(),
    staleTime: 10_000,
    refetchInterval: 30_000
  });

  const cluster = data?.cluster ?? null;
  const isEnabled = Boolean(data?.enabled && cluster);

  const { isHealthy, isDegraded, statusColor, leaderLabel } = useMemo(() => {
    if (!cluster) {
      return {
        isHealthy: false,
        isDegraded: false,
        statusColor: "var(--mui-palette-error-main)",
        leaderLabel: "No Leader"
      };
    }

    const peers = cluster.peers ?? [];
    const rawLeader = cluster.known_leader_id?.trim();
    const hasLeader = Boolean(rawLeader);
    const reachableCount = peers.filter((p) => Boolean(p.tcp_reachable)).length;
    const allReachable = peers.length > 0 ? reachableCount === peers.length : true;

    const healthy = hasLeader && allReachable;
    const degraded = hasLeader && reachableCount > 0 && !allReachable;

    const color = healthy
      ? "var(--mui-palette-success-main)"
      : degraded
        ? "var(--mui-palette-warning-main)"
        : "var(--mui-palette-error-main)";

    return {
      isHealthy: healthy,
      isDegraded: degraded,
      statusColor: color,
      leaderLabel: rawLeader || "Unknown"
    };
  }, [cluster]);

  // If loading, errored, or HA is disabled, do not display anything
  if (isLoading || isError || !isEnabled || !cluster) {
    return null;
  }

  const dotAnimation = isHealthy ? pulseGreen : isDegraded ? pulseOrange : "none";

  return (
    <>
      <Tooltip title="Raft Cluster" arrow placement="bottom">
        <Button
          onClick={() => setIsModalOpen(true)}
          aria-label={`Raft Cluster: ${leaderLabel}`}
          sx={[
            {
              display: "inline-flex",
              alignItems: "center",
              gap: 0.85,
              px: 1.5,
              py: 0.75,
              minWidth: "auto",
              minHeight: 36,
              borderRadius: "8px",
              textTransform: "none",
              color: "text.primary",
              backgroundColor: "transparent",
              border: "none",
              boxShadow: "none !important",
              transition: "opacity 150ms ease, background-color 150ms ease",
              "&:hover": {
                backgroundColor: "action.hover",
                boxShadow: "none !important"
              },
              "&:focus-visible": {
                outline: "2px solid var(--mui-palette-primary-main)",
                outlineOffset: 2
              }
            },
            (muiTheme) =>
              muiTheme.applyStyles("dark", {
                color: "var(--mui-palette-text-primary)"
              })
          ]}
        >
          <Box
            component="span"
            sx={{
              width: 7,
              height: 7,
              borderRadius: "50%",
              backgroundColor: statusColor,
              flexShrink: 0,
              animation: dotAnimation,
              "@media (prefers-reduced-motion: reduce)": {
                animation: "none"
              }
            }}
          />
          <Typography
            component="span"
            sx={{
              fontSize: "0.8125rem",
              fontWeight: 600,
              color: "inherit",
              lineHeight: 1,
              letterSpacing: "-0.01em"
            }}
          >
            {leaderLabel}
          </Typography>
        </Button>
      </Tooltip>

      <RaftClusterModal
        open={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        cluster={cluster}
      />
    </>
  );
}
