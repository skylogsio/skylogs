"use client";

import { Box, Chip, Stack, Typography } from "@mui/material";

import type { IRaftCluster } from "@/@types/ha";
import ModalContainer from "@/components/Modal";
import { createPulseAnimation } from "@/utils/animation";

const pulseGreen = createPulseAnimation("var(--mui-palette-success-main)");

interface RaftClusterModalProps {
  open: boolean;
  onClose: () => void;
  cluster: IRaftCluster | null;
}

export default function RaftClusterModal({ open, onClose, cluster }: RaftClusterModalProps) {
  const peers = cluster?.peers ?? [];
  const knownLeaderId = cluster?.known_leader_id?.trim();

  return (
    <ModalContainer open={open} onClose={onClose} title="Raft Cluster" maxWidth={420}>
      <Box
        sx={[
          {
            mt: 1.5,
            pt: 2,
            borderTop:
              "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 14%, transparent)"
          },
          (muiTheme) =>
            muiTheme.applyStyles("dark", {
              borderTop:
                "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 12%, transparent)"
            })
        ]}
      >
        <Stack spacing={2.5}>
          {/* Known Leader ID */}
          <Stack spacing={0.5}>
            <Typography
              variant="caption"
              sx={{
                color: "text.secondary",
                fontSize: "0.75rem",
                fontWeight: 600,
                letterSpacing: "0.04em",
                textTransform: "uppercase"
              }}
            >
              Leader ID
            </Typography>
            <Typography
              variant="body1"
              sx={{
                color: knownLeaderId ? "text.primary" : "text.secondary",
                fontWeight: 600,
                fontSize: "0.9375rem"
              }}
            >
              {knownLeaderId || "—"}
            </Typography>
          </Stack>

          {/* Nodes */}
          <Stack spacing={1}>
            <Typography
              variant="caption"
              sx={{
                color: "text.secondary",
                fontSize: "0.75rem",
                fontWeight: 600,
                letterSpacing: "0.04em",
                textTransform: "uppercase"
              }}
            >
              Nodes
            </Typography>

            {peers.length === 0 ? (
              <Typography
                variant="body2"
                sx={{
                  color: "text.secondary",
                  py: 1,
                  fontStyle: "italic",
                  fontSize: "0.85rem"
                }}
              >
                No nodes available
              </Typography>
            ) : (
              <Stack
                spacing={1}
                sx={{
                  maxHeight: 280,
                  overflowY: "auto",
                  pr: peers.length > 4 ? 0.5 : 0
                }}
              >
                {peers.map((peer) => {
                  const isReachable = Boolean(peer.tcp_reachable);
                  const statusColor = isReachable
                    ? "var(--mui-palette-success-main)"
                    : "var(--mui-palette-error-main)";
                  const isActive = Boolean(
                    cluster?.known_leader_id
                      ? peer.id === cluster.known_leader_id
                      : cluster?.reporter_id && peer.id === cluster.reporter_id
                  );

                  return (
                    <Box
                      key={peer.id}
                      sx={[
                        {
                          display: "flex",
                          alignItems: "center",
                          justifyContent: "space-between",
                          px: 1.5,
                          py: 1,
                          borderRadius: "8px",
                          backgroundColor: isActive
                            ? "color-mix(in srgb, var(--mui-palette-primary-main) 6%, #F1EBE1)"
                            : "#F1EBE1",
                          border: isActive
                            ? "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 40%, transparent)"
                            : "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 14%, transparent)",
                          transition: "border-color 150ms ease, background-color 150ms ease"
                        },
                        (muiTheme) =>
                          muiTheme.applyStyles("dark", {
                            backgroundColor: isActive
                              ? "color-mix(in srgb, var(--mui-palette-primary-main) 10%, rgba(255, 255, 255, 0.05))"
                              : "rgba(255, 255, 255, 0.05)",
                            border: isActive
                              ? "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 36%, transparent)"
                              : "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 10%, transparent)"
                          })
                      ]}
                    >
                      <Stack
                        direction="row"
                        spacing={1}
                        sx={{ alignItems: "center", minWidth: 0, pr: 1 }}
                      >
                        <Box
                          component="span"
                          sx={{
                            width: 8,
                            height: 8,
                            borderRadius: "50%",
                            flexShrink: 0,
                            backgroundColor: statusColor,
                            animation: isReachable ? pulseGreen : "none",
                            "@media (prefers-reduced-motion: reduce)": {
                              animation: "none"
                            }
                          }}
                        />
                        <Typography
                          variant="body2"
                          sx={{
                            fontWeight: 600,
                            color: "text.primary",
                            overflow: "hidden",
                            textOverflow: "ellipsis",
                            whiteSpace: "nowrap"
                          }}
                        >
                          {peer.id}
                        </Typography>
                        {isActive && (
                          <Chip
                            label="Active"
                            size="small"
                            sx={[
                              {
                                height: 18,
                                fontSize: "0.625rem",
                                fontWeight: 700,
                                letterSpacing: "0.04em",
                                textTransform: "uppercase",
                                color: "var(--mui-palette-primary-main)",
                                backgroundColor:
                                  "color-mix(in srgb, var(--mui-palette-primary-main) 14%, transparent)",
                                border:
                                  "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 30%, transparent)",
                                borderRadius: "4px",
                                "& .MuiChip-label": { px: 0.6 }
                              },
                              (muiTheme) =>
                                muiTheme.applyStyles("dark", {
                                  color: "var(--mui-palette-primary-light)",
                                  backgroundColor:
                                    "color-mix(in srgb, var(--mui-palette-primary-main) 18%, transparent)",
                                  border:
                                    "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 35%, transparent)"
                                })
                            ]}
                          />
                        )}
                      </Stack>

                      <Typography
                        variant="caption"
                        sx={{
                          fontWeight: 600,
                          fontSize: "0.75rem",
                          color: statusColor,
                          flexShrink: 0
                        }}
                      >
                        {isReachable ? "Reachable" : "Unreachable"}
                      </Typography>
                    </Box>
                  );
                })}
              </Stack>
            )}
          </Stack>
        </Stack>
      </Box>
    </ModalContainer>
  );
}
