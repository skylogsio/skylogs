"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { useTheme, alpha } from "@mui/material";

import type { CreateUpdateModal } from "@/@types/global";
import SmsConfigModal from "@/components/admin-area/ConnectivitySetting/Sms/SmsConfigModal";
import EmptyList from "@/components/EmptyList";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

export default function SmsPage() {
  const { palette } = useTheme();
  const router = useRouter();
  const [modalData, setModalData] = useState<CreateUpdateModal<{}>>(null);

  function handleRefreshData() {}

  const SmsIcon = ENDPOINT_CONFIG["sms"].icon;
  return (
    <>
      <EmptyList
        icon={<SmsIcon size="4.5rem" color={palette.common.white} />}
        title="No SMS Configuration Found"
        description="Set up your first SMS configuration to enable reliable text message delivery. SMS settings ensure notifications are sent successfully to users’ phones."
        actionLabel="Create First SMS Config"
        onAction={() => setModalData("NEW")}
        onBack={router.back}
        gradientColors={[palette.endpoint.sms, alpha(palette.endpoint.sms, 0.7)]}
      />
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
