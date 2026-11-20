"use client";

import { useMemo, useRef, useState } from "react";

import {
  alpha,
  Box,
  Button,
  Divider,
  IconButton,
  Popover,
  Skeleton,
  Stack,
  Typography
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { HiChevronDown, HiChevronUp, HiPencil, HiTrash } from "react-icons/hi";

import type { ICluster } from "@/@types/cluster";
import { getClusterStatus } from "@/api/cluster";
import { createPulseAnimation } from "@/utils/animation";
import { actionButtonSx } from "@/components/ActionColumn";

const pulseGreen = createPulseAnimation("var(--mui-palette-success-main)");
const pulseOrange = createPulseAnimation("var(--mui-palette-warning-main)");

interface AgentClusterCardProps {
  cluster: ICluster;
  onDelete: (cluster: ICluster) => void;
  onEdit?: (cluster: ICluster) => void;
}

export default function AgentClusterCard({ cluster, onDelete, onEdit }: AgentClusterCardProps) {
  const cardRef = useRef<HTMLDivElement>(null);
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
  const isExpanded = Boolean(anchorEl);

  const { data: status, isLoading: isStatusLoading } = useQuery({
    queryKey: ["agent-cluster-status", cluster.id],
    queryFn: () => getClusterStatus(cluster.id),
    refetchInterval: 10_000,
    staleTime: 5_000
  });

  const isConnected = Boolean(status?.isConnected);
  const haCluster = status?.cluster ?? null;
  const haPeers = useMemo(() => haCluster?.peers ?? [], [haCluster]);
  const hasHaNodes = isConnected && haPeers.length > 0;
  const leaderId = haCluster?.known_leader_id?.trim();

  const isAllDisconnected = !isConnected || (haPeers.length > 0 && haPeers.every((p) => !p.tcp_reachable));
  const hasDisconnectedNode = !isAllDisconnected && haPeers.some((p) => !p.tcp_reachable);
  const statusColor = isAllDisconnected ? "error" : hasDisconnectedNode ? "warning" : "success";

  const handleToggleDetails = (event: React.MouseEvent<HTMLElement>) => {
    if (isExpanded) {
      setAnchorEl(null);
    } else {
      setAnchorEl(cardRef.current);
    }
  };

  return (
    <Box
      ref={cardRef}
      sx={{
        backgroundColor: "background.paper",
        borderRadius: "16px",
        border: "1px solid",
        borderColor: (theme) =>
          isExpanded ? alpha(theme.palette.primary.main, 0.45) : alpha(theme.palette.divider, 0.6),
        p: 2.5,
        cursor: "default",
        display: "flex",
        flexDirection: "column",
        justifyContent: "space-between",
        transition: "box-shadow 150ms ease, border-color 150ms ease",
        "&:hover": {
          borderColor: (theme) => alpha(theme.palette.primary.main, 0.4)
        }
      }}
    >
      <Box>
        <Box
          sx={{
            display: "flex",
            justifyContent: "space-between",
            alignItems: "flex-start"
          }}
        >
          <Box sx={{ minWidth: 0, pr: 1 }}>
            <Box sx={{ display: "flex", alignItems: "center", gap: 1, flexWrap: "wrap" }}>
              <Typography
                variant="subtitle1"
                sx={{
                  fontWeight: 700,
                  fontSize: "1rem",
                  color: "text.primary",
                  whiteSpace: "nowrap",
                  overflow: "hidden",
                  textOverflow: "ellipsis"
                }}
              >
                {cluster.name}
              </Typography>
              <Typography
                variant="caption"
                sx={{
                  color: "text.secondary",
                  fontSize: 13,
                  fontWeight: 500
                }}
              >
                Agent
              </Typography>
            </Box>

            <Typography
              variant="body2"
              sx={{
                color: "text.secondary",
                fontSize: 13,
                mt: 0.5,
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis"
              }}
            >
              {cluster.url}
            </Typography>
          </Box>

          <Box sx={{ display: "flex", alignItems: "center", gap: 0.5, flexShrink: 0 }}>
            {onEdit && (
              <IconButton
                onClick={() => onEdit(cluster)}
                size="small"
                aria-label={`Edit ${cluster.name}`}
                sx={actionButtonSx("primary.main")}
              >
                <HiPencil size={18} />
              </IconButton>
            )}
            <IconButton
              onClick={() => onDelete(cluster)}
              size="small"
              aria-label={`Delete ${cluster.name}`}
              sx={actionButtonSx("error.main")}
            >
              <HiTrash size={18} />
            </IconButton>
          </Box>
        </Box>

        <Box sx={{ mt: 1.5 }}>
          {isStatusLoading ? (
            <Skeleton variant="rounded" width={85} height={24} sx={{ borderRadius: "6px" }} />
          ) : (
            <Box
              sx={{
                display: "inline-flex",
                alignItems: "center",
                px: 1.5,
                py: 0.4,
                borderRadius: "6px",
                fontSize: 13,
                fontWeight: 500,
                backgroundColor: (theme) => alpha(theme.palette[statusColor].main, 0.08),
                color: `${statusColor}.main`
              }}
            >
              {!isAllDisconnected && (
                <Box
                  component="span"
                  sx={{
                    width: 6,
                    height: 6,
                    borderRadius: "50%",
                    backgroundColor: "currentColor",
                    mr: 0.75,
                    animation: hasDisconnectedNode ? pulseOrange : pulseGreen,
                    "@media (prefers-reduced-motion: reduce)": {
                      animation: "none"
                    }
                  }}
                />
              )}
              {isAllDisconnected ? "Disconnected" : "Connected"}
            </Box>
          )}
        </Box>

        <Divider sx={{ my: 2, borderColor: "divider" }} />

        {!hasHaNodes ? (
          <Typography variant="body2" sx={{ color: "text.secondary", fontSize: 13 }}>
            No HA nodes configured
          </Typography>
        ) : (
          <Box
            sx={{
              display: "flex",
              justifyContent: "space-between",
              alignItems: "center"
            }}
          >
            <Typography
              variant="body2"
              sx={{ color: "text.secondary", fontSize: 13, fontWeight: 500 }}
            >
              HA · {haPeers.length} nodes
            </Typography>

            <Button
              size="small"
              onClick={handleToggleDetails}
              endIcon={
                <Box
                  component={isExpanded ? HiChevronUp : HiChevronDown}
                  sx={{ fontSize: 14, display: "flex" }}
                />
              }
              sx={{
                minWidth: "auto",
                p: 0,
                color: "text.secondary",
                fontSize: 13,
                fontWeight: 500,
                textTransform: "none",
                "&:hover": {
                  backgroundColor: "transparent",
                  color: "text.primary"
                }
              }}
            >
              {isExpanded ? "Hide details" : "Details"}
            </Button>
          </Box>
        )}
      </Box>

      {hasHaNodes && (
        <Popover
          open={isExpanded}
          anchorEl={anchorEl}
          onClose={() => setAnchorEl(null)}
          anchorOrigin={{
            vertical: "bottom",
            horizontal: "left"
          }}
          transformOrigin={{
            vertical: "top",
            horizontal: "left"
          }}
          slotProps={{
            paper: {
              sx: {
                width: cardRef.current ? cardRef.current.offsetWidth : 360,
                maxWidth: "calc(100vw - 32px)",
                maxHeight: "calc(100vh - 120px)",
                overflowY: "auto",
                backgroundColor: "background.paper",
                borderRadius: "14px",
                border: "1px solid",
                borderColor: (theme) => alpha(theme.palette.primary.main, 0.4),
                boxShadow: (theme) =>
                  theme.palette.mode === "dark"
                    ? "0 12px 32px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.08)"
                    : "0 12px 32px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.04)",
                p: 2,
                mt: 1
              }
            }
          }}
        >
          <Box
            sx={{
              display: "flex",
              justifyContent: "space-between",
              alignItems: "center",
              mb: 1.5
            }}
          >
            <Typography
              variant="body2"
              sx={{ color: "text.secondary", fontSize: 13, fontWeight: 600 }}
            >
              High availability · {haPeers.length} nodes
            </Typography>

            <Button
              size="small"
              onClick={() => setAnchorEl(null)}
              endIcon={<HiChevronUp size={14} />}
              sx={{
                minWidth: "auto",
                p: 0,
                color: "text.secondary",
                fontSize: 13,
                fontWeight: 500,
                textTransform: "none",
                "&:hover": {
                  backgroundColor: "transparent",
                  color: "text.primary"
                }
              }}
            >
              Hide details
            </Button>
          </Box>

          <Stack spacing={1}>
            {haPeers.map((peer) => {
              const isLeader = Boolean(leaderId && peer.id === leaderId);
              const isReachable = Boolean(peer.tcp_reachable);

              return (
                <Box
                  key={peer.id}
                  sx={{
                    p: 1.5,
                    borderRadius: "8px",
                    backgroundColor: isLeader
                      ? (theme) =>
                          theme.palette.mode === "dark"
                            ? alpha(theme.palette.info.main, 0.15)
                            : "#EDF5FD"
                      : (theme) =>
                          theme.palette.mode === "dark" ? "rgba(255, 255, 255, 0.03)" : "#FAF8F5",
                    border: "1px solid",
                    borderColor: isLeader
                      ? (theme) => alpha(theme.palette.info.main, 0.3)
                      : "transparent",
                    display: "flex",
                    flexDirection: "column",
                    gap: 0.25
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
                      {peer.id}
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
            })}
          </Stack>
        </Popover>
      )}
    </Box>
  );
}
