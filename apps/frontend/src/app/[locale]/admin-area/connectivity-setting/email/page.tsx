"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { useTheme, alpha } from "@mui/material";

import type { CreateUpdateModal } from "@/@types/global";
import EmailConfigModal from "@/components/admin-area/ConnectivitySetting/email/EmailConfigModal";
import EmptyList from "@/components/EmptyList";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

export default function EmailPage() {
  const { palette } = useTheme();
  const router = useRouter();
  const [modalData, setModalData] = useState<CreateUpdateModal<{}>>(null);

  function handleRefreshData() {}

  const EmailIcon = ENDPOINT_CONFIG["email"].icon;
  return (
    <>
      <EmptyList
        icon={<EmailIcon size="4.5rem" color={palette.common.white} />}
        title="No Email Configuration Found"
        description="Set up your first email configuration to enable secure and reliable email delivery. Email settings ensure notifications are delivered to users’ inboxes."
        actionLabel="Create First Email Config"
        onAction={() => setModalData("NEW")}
        onBack={router.back}
        gradientColors={[palette.endpoint.email, alpha(palette.endpoint.email, 0.7)]}
      />
      {modalData && (
        <EmailConfigModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
    </>
  );
}
