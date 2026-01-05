"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { useTheme, alpha, Grid2 as Grid, Stack, Typography, Button } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { HiOutlinePlusSm } from "react-icons/hi";

import type { IEmailConfig } from "@/@types/admin-area/emailConfig";
import type { CreateUpdateModal } from "@/@types/global";
import { getAllEmailConfigs } from "@/api/admin-area/emailConfig";
import DeleteEmailConfigModal from "@/components/admin-area/ConnectivitySetting/email/DeleteEmailConfigModal";
import { EmailConfigCard } from "@/components/admin-area/ConnectivitySetting/email/EmailConfigCard";
import EmailConfigModal from "@/components/admin-area/ConnectivitySetting/email/EmailConfigModal";
import EmptyList from "@/components/EmptyList";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

export default function EmailPage() {
  const { palette } = useTheme();
  const router = useRouter();
  const [modalData, setModalData] = useState<CreateUpdateModal<IEmailConfig>>(null);
  const [deleteModalData, setDeleteModalData] = useState<IEmailConfig | null>(null);

  const { data, refetch } = useQuery({
    queryKey: ["email-configs"],
    queryFn: () => getAllEmailConfigs()
  });

  function handleRefreshData() {
    refetch();
  }

  function handleDeleteModalClose() {
    setDeleteModalData(null);
    handleRefreshData();
  }

  const EmailIcon = ENDPOINT_CONFIG["email"].icon;

  return (
    <>
      {data && data.length > 0 ? (
        <>
          <Stack
            direction="row"
            justifyContent="space-between"
            alignItems="flex-end"
            marginBottom={5}
          >
            <Typography variant="h5" fontSize="1.8rem" fontWeight="700" component="div">
              Email Configurations
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
              <Grid key={item.id} size={{ xs: 6, lg: 4, xl: 3 }}>
                <EmailConfigCard
                  config={item}
                  onEdit={() => setModalData(item)}
                  onDelete={() => setDeleteModalData(item)}
                />
              </Grid>
            ))}
          </Grid>
        </>
      ) : (
        <EmptyList
          icon={<EmailIcon size="4.5rem" color={palette.common.white} />}
          title="No Email Configuration Found"
          description="Set up your first email configuration to enable secure and reliable email delivery. Email settings ensure notifications are delivered to users' inboxes."
          actionLabel="Create First Email Config"
          onAction={() => setModalData("NEW")}
          onBack={router.back}
          gradientColors={[palette.endpoint.email, alpha(palette.endpoint.email, 0.7)]}
        />
      )}
      {modalData && (
        <EmailConfigModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
      {deleteModalData && (
        <DeleteEmailConfigModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          data={deleteModalData}
          onAfterDelete={handleDeleteModalClose}
        />
      )}
    </>
  );
}
