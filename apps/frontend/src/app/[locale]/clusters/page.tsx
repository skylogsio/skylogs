"use client";

import { useState } from "react";

import { Box, Button, Skeleton, Typography } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { IoAddOutline } from "react-icons/io5";

import type { ICluster } from "@/@types/cluster";
import type { CreateUpdateModal } from "@/@types/global";
import { getAllClusters } from "@/api/cluster";

import ClusterModal from "./ClusterModal";
import DeleteClusterModal from "./DeleteClusterModal";
import AgentClusterCard from "./components/AgentClusterCard";
import MainClusterCard from "./components/MainClusterCard";
import NoAgentClustersEmptyState from "./components/NoAgentClustersEmptyState";

export default function ClustersPage() {
  const [modalData, setModalData] = useState<CreateUpdateModal<ICluster>>(null);
  const [deleteModalData, setDeleteModalData] = useState<ICluster | null>(null);

  const {
    data: clusters = [],
    isLoading,
    refetch
  } = useQuery({
    queryKey: ["all-clusters"],
    queryFn: () => getAllClusters()
  });

  return (
    <Box sx={{ maxWidth: 1400, mx: "auto", pb: 6 }}>
      <Typography
        variant="h5"
        sx={{
          fontWeight: 700,
          fontSize: "1.5rem",
          color: "text.primary",
          mb: 3
        }}
      >
        Clusters
      </Typography>

      <MainClusterCard />

      <Box
        sx={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          mt: 4,
          mb: 2.5
        }}
      >
        <Typography
          variant="h6"
          sx={{
            fontWeight: 700,
            fontSize: "1.125rem",
            color: "text.primary"
          }}
        >
          Agent Clusters
        </Typography>

        <Button
          variant="contained"
          startIcon={<IoAddOutline size={18} />}
          onClick={() => setModalData("NEW")}
          disabled={clusters.length >= 9}
          sx={{
            backgroundColor: "primary.main",
            color: "primary.contrastText",
            borderRadius: "8px",
            fontWeight: 600,
            fontSize: "0.875rem",
            textTransform: "none",
            px: 2,
            py: 0.85,
            boxShadow: "none",
            "&:hover": {
              backgroundColor: "primary.dark",
              boxShadow: "none"
            }
          }}
        >
          Add Agent Cluster
        </Button>
      </Box>

      {isLoading ? (
        <Box
          sx={{
            display: "grid",
            gridTemplateColumns: {
              xs: "1fr",
              md: "repeat(2, 1fr)",
              lg: "repeat(3, 1fr)"
            },
            gap: 2.5
          }}
        >
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} variant="rounded" height={170} sx={{ borderRadius: "16px" }} />
          ))}
        </Box>
      ) : clusters.length === 0 ? (
        <NoAgentClustersEmptyState />
      ) : (
        <Box
          sx={{
            display: "grid",
            gridTemplateColumns: {
              xs: "1fr",
              md: "repeat(2, 1fr)",
              lg: "repeat(3, 1fr)"
            },
            gap: 2.5
          }}
        >
          {clusters.map((cluster) => (
            <AgentClusterCard
              key={cluster.id}
              cluster={cluster}
              onDelete={(target) => setDeleteModalData(target)}
            />
          ))}
        </Box>
      )}

      {modalData && (
        <ClusterModal
          open={Boolean(modalData)}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={() => refetch()}
        />
      )}

      {deleteModalData && (
        <DeleteClusterModal
          open={Boolean(deleteModalData)}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={() => {
            setDeleteModalData(null);
            refetch();
          }}
        />
      )}
    </Box>
  );
}
