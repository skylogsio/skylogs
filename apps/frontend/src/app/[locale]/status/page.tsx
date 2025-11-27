"use client";

import { useState } from "react";

import { Box, Button, Stack, Typography } from "@mui/material";
import { useQuery, useMutation } from "@tanstack/react-query";
import { HiOutlinePlusSm } from "react-icons/hi";
import { toast } from "react-toastify";

import { CreateUpdateModal } from "@/@types/global";
import { IStatusCard } from "@/@types/status";
import { getAllStatus, deleteStatusCard } from "@/api/status";
import StatusMonitoringCards from "@/components/Status/StatusCard";
import StatusCardModal from "@/components/Status/StatusCardModal";
import StatusCardSkeleton from "@/components/Status/StatusCardSkeleton";
import StatusDeleteConfirmationDialog from "@/components/Status/StatusDeleteConfirmationDialog";
import { useRole } from "@/hooks";

export default function StatusPage() {
  const [modalData, setModalData] = useState<CreateUpdateModal<IStatusCard>>(null);
  const [deleteDialogData, setDeleteDialogData] = useState<IStatusCard | null>(null);
  const { hasRole } = useRole();
  const isOwner = hasRole("owner");

  const { data: statusCards, refetch } = useQuery({
    queryKey: ["all-status-cards"],
    queryFn: () => getAllStatus(),
    refetchInterval: 5 * 1000
  });

  const { mutate: deleteStatusMutation, isPending: isDeleting } = useMutation({
    mutationFn: (id: IStatusCard["id"]) => deleteStatusCard(id),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Status Card Deleted Successfully.");
        refetch();
        setDeleteDialogData(null);
      }
    },
    onError: () => {
      toast.error("Failed to delete status card.");
    }
  });

  function renderSkeletonCards() {
    return Array.from({ length: 6 }, (_, index) => (
      <StatusCardSkeleton key={`skeleton-${index}`} />
    ));
  }

  const handleEdit = (statusCard: IStatusCard) => {
    setModalData(statusCard);
  };

  const handleDelete = (statusCard: IStatusCard) => {
    setDeleteDialogData(statusCard);
  };

  const handleDeleteConfirm = () => {
    if (deleteDialogData) {
      deleteStatusMutation(deleteDialogData.id);
    }
  };

  const handleDeleteCancel = () => {
    setDeleteDialogData(null);
  };

  return (
    <>
      <Stack spacing={3}>
        <Stack direction="row" justifyContent="space-between">
          <Typography variant="h5" fontSize="1.8rem" fontWeight="700" component="div">
            Status
          </Typography>
          {isOwner && (
            <Button
              startIcon={<HiOutlinePlusSm size="1.3rem" />}
              onClick={() => setModalData("NEW")}
              size="small"
              variant="contained"
              sx={{ paddingRight: "1rem" }}
            >
              Create
            </Button>
          )}
        </Stack>
        <Box
          sx={{
            display: "flex",
            flexWrap: "wrap",
            gap: 3,
            paddingY: 4,
            justifyContent: "center",
            alignItems: "flex-start",
            margin: "0 auto",
            borderRadius: 3,
            backgroundColor: ({ palette }) => palette.background.paper
          }}
        >
          {statusCards
            ? statusCards?.map((item) => (
                <StatusMonitoringCards
                  key={item.id}
                  info={item}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                />
              ))
            : renderSkeletonCards()}
        </Box>
      </Stack>

      {isOwner && modalData && (
        <StatusCardModal
          data={modalData}
          open={!!modalData}
          onClose={() => setModalData(null)}
          onSubmit={() => refetch()}
        />
      )}

      <StatusDeleteConfirmationDialog
        open={!!deleteDialogData}
        statusCard={deleteDialogData}
        onConfirm={handleDeleteConfirm}
        onClose={handleDeleteCancel}
        isLoading={isDeleting}
      />
    </>
  );
}
