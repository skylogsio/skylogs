"use client";

import { alpha, Box, Typography } from "@mui/material";

import type { IRaftPeer } from "@/@types/ha";
import { createPulseAnimation } from "@/utils/animation";

const pulseGreen = createPulseAnimation("var(--mui-palette-success-main)");

interface MainNodeCardProps {
  peer: IRaftPeer;
  isLeader?: boolean;
}

export default function MainNodeCard({ peer, isLeader = false }: MainNodeCardProps) {
  const isReachable = Boolean(peer.tcp_reachable);

  return (
    <Box
      sx={{
        p: 2,
        borderRadius: "10px",
        backgroundColor: (theme) =>
          isLeader
            ? theme.palette.mode === "dark"
              ? alpha(theme.palette.info.main, 0.15)
              : "#EDF5FD"
            : theme.palette.mode === "dark"
              ? "rgba(255, 255, 255, 0.03)"
              : "#FAF8F5",
        border: "none",
        outline: "none",
        display: "flex",
        flexDirection: "column",
        gap: 0.5,
        minWidth: 160
      }}
    >
      <Box sx={{ display: "flex", alignItems: "center" }}>
        <Typography
          variant="body2"
          sx={{
            fontWeight: 600,
            fontSize: "0.875rem",
            color: "text.primary",
            whiteSpace: "nowrap",
            overflow: "hidden",
            textOverflow: "ellipsis"
          }}
        >
          ID: {peer.id}
        </Typography>
        {isLeader && (
          <Typography
            variant="caption"
            sx={{
              ml: 1,
              fontWeight: 700,
              fontSize: 13,
              color: "info.main"
            }}
          >
            Leader
          </Typography>
        )}
      </Box>

      <Typography
        variant="caption"
        sx={{
          color: "text.secondary",
          fontSize: 13,
          whiteSpace: "nowrap",
          overflow: "hidden",
          textOverflow: "ellipsis"
        }}
      >
        {peer.address || "—"}
      </Typography>

      <Typography
        variant="caption"
        sx={{
          color: "text.secondary",
          fontSize: 13
        }}
      >
        Suffrage: {peer.suffrage || "Voter"}
      </Typography>

      <Box sx={{ display: "flex", alignItems: "center", gap: 0.75, mt: 0.5 }}>
        <Box
          component="span"
          sx={{
            width: 6,
            height: 6,
            borderRadius: "50%",
            flexShrink: 0,
            backgroundColor: isReachable ? "success.main" : "error.main",
            animation: isReachable ? pulseGreen : "none",
            "@media (prefers-reduced-motion: reduce)": {
              animation: "none"
            }
          }}
        />
        <Typography
          variant="caption"
          sx={{
            fontWeight: 500,
            fontSize: 13,
            color: isReachable ? "success.main" : "error.main",
            whiteSpace: "nowrap"
          }}
        >
          {isReachable ? "TCP reachable" : "TCP not reachable"}
        </Typography>
      </Box>
    </Box>
  );
}
