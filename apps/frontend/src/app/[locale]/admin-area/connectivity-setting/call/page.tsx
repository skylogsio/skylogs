"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { useTheme, alpha } from "@mui/material";

import type { CreateUpdateModal } from "@/@types/global";
import CallConfigModal from "@/components/admin-area/ConnectivitySetting/Call/CallConfigModal";
import EmptyList from "@/components/EmptyList";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

export default function CallPage() {
  const { palette } = useTheme();
  const router = useRouter();
  const [modalData, setModalData] = useState<CreateUpdateModal<{}>>(null);

  function handleRefreshData() {}

  const CallIcon = ENDPOINT_CONFIG["call"].icon;
  return (
    <>
      <EmptyList
        icon={<CallIcon size="4.5rem" color={palette.common.white} />}
        title="No Call Configuration Found"
        description="Set up your first call configuration to enable reliable voice call delivery. Call settings ensure automated calls reach users without interruption."
        actionLabel="Create First Call Config"
        onAction={() => setModalData("NEW")}
        onBack={router.back}
        gradientColors={[palette.endpoint.call, alpha(palette.endpoint.call, 0.7)]}
      />
      {modalData && (
        <CallConfigModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
    </>
  );
}
