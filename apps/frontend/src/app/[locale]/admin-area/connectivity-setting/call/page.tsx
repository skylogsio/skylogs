"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { useTheme, alpha, Grid2 as Grid, Typography, Button, Stack } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { HiOutlinePlusSm } from "react-icons/hi";
import { IoIosArrowBack } from "react-icons/io";

import { ICallConfig } from "@/@types/admin-area/callConfig";
import type { CreateUpdateModal } from "@/@types/global";
import { getAllCallConfigs } from "@/api/admin-area/callConfig";
import { CallConfigCard } from "@/components/admin-area/ConnectivitySetting/Call/CallConfigCard";
import CallConfigModal from "@/components/admin-area/ConnectivitySetting/Call/CallConfigModal";
import DeleteCallConfigModal from "@/components/admin-area/ConnectivitySetting/Call/DeleteCallConfigModal";
import EmptyList from "@/components/EmptyList";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

export default function CallPage() {
  const { palette } = useTheme();
  const router = useRouter();
  const [modalData, setModalData] = useState<CreateUpdateModal<ICallConfig>>(null);
  const [deleteModalData, setDeleteModalData] = useState<ICallConfig | null>(null);

  const { data, refetch } = useQuery({
    queryKey: ["call-configs"],
    queryFn: () => getAllCallConfigs()
  });

  function handleRefreshData() {
    refetch();
  }

  function handleDeleteModalClose() {
    setDeleteModalData(null);
    handleRefreshData();
  }

  const CallIcon = ENDPOINT_CONFIG["call"].icon;

  return (
    <>
      {data && data.length > 0 ? (
        <>
          <Stack
            direction="row"
            justifyContent="space-between"
            alignItems="center"
            marginBottom={3}
          >
            <Stack direction="row" alignItems="center" spacing={2}>
              <Button
                onClick={() => router.back()}
                sx={{
                  minWidth: "auto",
                  padding: "0.5rem",
                  backgroundColor: alpha(palette.primary.light, 0.08),
                  "&:hover": {
                    backgroundColor: alpha(palette.primary.light, 0.15)
                  }
                }}
              >
                <IoIosArrowBack size="1.5rem" />
              </Button>
              <Typography variant="h5" fontSize="1.8rem" fontWeight="700" component="div">
                Call Configurations
              </Typography>
            </Stack>
            <Button
              startIcon={<HiOutlinePlusSm size="1.3rem" />}
              onClick={() => setModalData("NEW")}
              size="small"
              variant="contained"
              sx={{ paddingRight: "1rem" }}
            >
              Create
            </Button>
          </Stack>
          <Grid container spacing={3}>
            {data.map((item) => (
              <Grid key={item.id} size={{ xs: 6, lg: 4, xl: 3 }}>
                <CallConfigCard
                  config={item}
                  onEdit={() => setModalData(item)}
                  onDelete={() => setDeleteModalData(item)}
                  onSetAsDefault={handleRefreshData}
                />
              </Grid>
            ))}
          </Grid>
        </>
      ) : (
        <EmptyList
          icon={<CallIcon size="4.5rem" color={palette.common.white} />}
          title="No Call Configuration Found"
          description="Set up your first call configuration to enable reliable voice call delivery. Call settings ensure automated calls reach users without interruption."
          actionLabel="Create First Call Config"
          onAction={() => setModalData("NEW")}
          onBack={router.back}
          gradientColors={[palette.endpoint.call, alpha(palette.endpoint.call, 0.7)]}
        />
      )}
      {modalData && (
        <CallConfigModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
      {deleteModalData && (
        <DeleteCallConfigModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={handleDeleteModalClose}
        />
      )}
    </>
  );
}
