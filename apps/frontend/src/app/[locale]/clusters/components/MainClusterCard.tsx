"use client";

import { useMemo } from "react";

import { alpha, Box, Divider, Skeleton, Typography } from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import { getHaStatus } from "@/api/ha";
import { createPulseAnimation } from "@/utils/animation";
import MainNodeCard from "./MainNodeCard";

const pulseGreen = createPulseAnimation("var(--mui-palette-success-main)");

export default function MainClusterCard() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ["main-ha-status"],
    queryFn: () => getHaStatus(),
    refetchInterval: 10_000,
    staleTime: 5_000
  });

  const cluster = data?.cluster ?? null;
  const peers = useMemo(() => cluster?.peers ?? [], [cluster]);
  const leaderId = cluster?.known_leader_id?.trim() || "";
  const isConnected = !isError && Boolean(data !== undefined && (data.enabled || !isError));

  return (
    <Box
      sx={{
        backgroundColor: "background.paper",
        borderRadius: "16px",
        border: "1px solid",
        borderColor: "divider",
        p: 3,
        mb: 4
      }}
    >
      <Box
        sx={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "flex-start"
        }}
      >
        <Box>
          <Box sx={{ display: "flex", alignItems: "center", gap: 1 }}>
            <Typography
              variant="h6"
              sx={{ fontWeight: 700, fontSize: "1.125rem", color: "text.primary" }}
            >
              main-cluster
            </Typography>
            <Typography
              variant="caption"
              sx={{
                color: "text.secondary",
                fontSize: 13,
                fontWeight: 500
              }}
            >
              Main
            </Typography>
          </Box>

          <Typography
            variant="body2"
            sx={{
              color: "text.secondary",
              fontSize: 13,
              mt: 0.5
            }}
          >
            {cluster?.known_leader_address}
          </Typography>
        </Box>

        {isLoading ? (
          <Skeleton variant="rounded" width={90} height={26} sx={{ borderRadius: "6px" }} />
        ) : (
          <Box
            sx={{
              display: "inline-flex",
              alignItems: "center",
              px: 1.5,
              py: 0.45,
              borderRadius: "6px",
              fontSize: 13,
              fontWeight: 500,
              backgroundColor: isConnected
                ? (theme) => alpha(theme.palette.success.main, 0.08)
                : (theme) => alpha(theme.palette.error.main, 0.08),
              color: isConnected ? "success.main" : "error.main"
            }}
          >
            {isConnected && (
              <Box
                component="span"
                sx={{
                  width: 6,
                  height: 6,
                  borderRadius: "50%",
                  backgroundColor: "currentColor",
                  mr: 0.75,
                  animation: pulseGreen,
                  "@media (prefers-reduced-motion: reduce)": {
                    animation: "none"
                  }
                }}
              />
            )}
            {isConnected ? "Connected" : "Disconnected"}
          </Box>
        )}
      </Box>

      {isConnected && (
        <>
          <Divider sx={{ my: 2.5, borderColor: "divider" }} />

          {peers.length === 0 ? (
            <Typography variant="body2" sx={{ color: "text.secondary", fontSize: "0.875rem" }}>
              No HA nodes configured
            </Typography>
          ) : (
            <Box>
              <Box sx={{ display: "flex", alignItems: "center", gap: 1, mb: 1 }}>
                <Typography
                  variant="subtitle2"
                  sx={{ fontWeight: 600, fontSize: "0.875rem", color: "text.primary" }}
                >
                  High availability
                </Typography>
                <Typography variant="body2" sx={{ color: "text.secondary", fontSize: "0.875rem" }}>
                  {peers.length} nodes
                </Typography>
              </Box>

              <Box sx={{ overflowX: "auto", pb: 0.5, pt: 0.5 }}>
                <Box
                  sx={{
                    minWidth: peers.length > 3 ? peers.length * 190 : "100%",
                    position: "relative"
                  }}
                >
                  <Box
                    sx={{
                      display: "grid",
                      gridTemplateColumns: `repeat(${peers.length}, 1fr)`,
                      gap: 2
                    }}
                  >
                    {peers.map((peer) => {
                      const isLeader = Boolean(
                        leaderId
                          ? peer.id === leaderId
                          : cluster?.reporter_id && peer.id === cluster.reporter_id
                      );
                      return <MainNodeCard key={peer.id} peer={peer} isLeader={isLeader} />;
                    })}
                  </Box>
                </Box>
              </Box>
            </Box>
          )}
        </>
      )}
    </Box>
  );
}
