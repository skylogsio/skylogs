"use client";

import { Box, Typography } from "@mui/material";

export default function NoAgentClustersEmptyState() {
  return (
    <Box
      sx={{
        backgroundColor: "background.paper",
        borderRadius: "16px",
        border: "1px solid",
        borderColor: "divider",
        py: 8,
        px: 3,
        textAlign: "center",
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center"
      }}
    >
      <Typography
        variant="subtitle1"
        sx={{ fontWeight: 700, color: "text.primary", fontSize: "1rem", mb: 0.5 }}
      >
        No Agent Clusters
      </Typography>
      <Typography variant="body2" sx={{ color: "text.secondary", fontSize: "0.875rem" }}>
        Add an Agent Cluster to main-cluster.
      </Typography>
    </Box>
  );
}
