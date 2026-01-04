"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { useTheme, Grid2 as Grid, Typography, alpha, Button, Stack } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { HiOutlinePlusSm } from "react-icons/hi";

import type { ISmsConfig } from "@/@types/admin-area/smsConfig";
import type { CreateUpdateModal } from "@/@types/global";
import { getAllSmsConfigs } from "@/api/admin-area/smsConfig";
import { SmsConfigCard } from "@/components/admin-area/ConnectivitySetting/Sms/SmsConfigCard";
import SmsConfigModal from "@/components/admin-area/ConnectivitySetting/Sms/SmsConfigModal";
import EmptyList from "@/components/EmptyList";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

export default function SmsPage() {
  const { palette } = useTheme();
  const router = useRouter();
  const [modalData, setModalData] = useState<CreateUpdateModal<ISmsConfig>>(null);

  const { data, refetch } = useQuery({
    queryKey: ["sms-configs"],
    queryFn: () => getAllSmsConfigs()
  });

  function handleRefreshData() {
    refetch();
  }

  const SmsIcon = ENDPOINT_CONFIG["sms"].icon;
  return (
    <>
      {data && data?.length > 0 ? (
        <>
          <Stack
            direction="row"
            justifyContent="space-between"
            alignItems="flex-end"
            marginBottom={5}
          >
            <Typography variant="h5" fontSize="1.8rem" fontWeight="700" component="div">
              SMS Configurations
            </Typography>
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
              <Grid key={item.id} size={3}>
                <SmsConfigCard config={item} onEdit={() => setModalData(item)} />
              </Grid>
            ))}
          </Grid>
        </>
      ) : (
        <EmptyList
          icon={<SmsIcon size="4.5rem" color={palette.common.white} />}
          title="No SMS Configuration Found"
          description="Set up your first SMS configuration to enable reliable text message delivery. SMS settings ensure notifications are sent successfully to users’ phones."
          actionLabel="Create First SMS Config"
          onAction={() => setModalData("NEW")}
          onBack={router.back}
          gradientColors={[palette.endpoint.sms, alpha(palette.endpoint.sms, 0.7)]}
        />
      )}
      {modalData && (
        <SmsConfigModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
    </>
  );
}
