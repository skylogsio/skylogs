"use client";

import { useRouter } from "next/navigation";

import { useTheme, alpha } from "@mui/material";

import EmptyList from "@/components/EmptyList";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

export default function SmsPage() {
  const { palette } = useTheme();
  const router = useRouter();
  const SmsIcon = ENDPOINT_CONFIG["sms"].icon;
  return (
    <EmptyList
      icon={<SmsIcon size="4.5rem" color={palette.common.white} />}
      title="No SMS Configuration Found"
      description="Set up your first SMS configuration to enable reliable text message delivery. SMS settings ensure notifications are sent successfully to users’ phones."
      actionLabel="Create First SMS Config"
      onAction={() => console.log("NEW")}
      onBack={router.back}
      gradientColors={[palette.endpoint.sms, alpha(palette.endpoint.sms, 0.7)]}
    />
  );
}
