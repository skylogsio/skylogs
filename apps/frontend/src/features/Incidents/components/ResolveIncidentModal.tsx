"use client";

import { useState } from "react";

import { Button, Stack, Typography, useTheme } from "@mui/material";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "react-toastify";

import DateTimeInput from "@/components/DateTimeInput";
import GradientSubmitButton from "@/components/GradientSubmitButton";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

import { resolveIncident } from "../incident.api";
import type { IIncident } from "../incident.type";

import IncidentModalBody from "./IncidentModalBody";

type ResolveIncidentModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: IIncident;
  onSubmit: () => void;
};

export default function ResolveIncidentModal({
  open,
  onClose,
  data,
  onSubmit
}: ResolveIncidentModalProps) {
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const queryClient = useQueryClient();
  const [resolvedAt, setResolvedAt] = useState<string | null>(null);

  const { mutate: resolveIncidentMutation, isPending } = useMutation({
    mutationFn: () => resolveIncident(data.id, resolvedAt ? { resolvedAt } : {}),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Incident Resolved Successfully.");
        void queryClient.invalidateQueries({ queryKey: ["incident"] });
        onSubmit();
        onClose?.();
        return;
      }
      toast.error(response.message);
    }
  });

  return (
    <ModalContainer
      title="Resolve Incident"
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
      maxWidth={480}
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <IncidentModalBody>
        <Stack spacing={2.5}>
          <Typography variant="body2" sx={{ color: "text.secondary" }}>
            Resolve <strong>{data.title}</strong>? Leave the time empty to use now.
          </Typography>
          <DateTimeInput
            calendar="gregorian"
            type="date-time"
            label="Resolved At"
            value={resolvedAt}
            onChange={(payload) => setResolvedAt(payload.iso)}
          />
          <Stack direction="row" spacing={1.5}>
            <Button
              fullWidth
              variant="outlined"
              onClick={onClose}
              disabled={isPending}
              sx={{ textTransform: "none", fontWeight: 600 }}
            >
              Cancel
            </Button>
            <GradientSubmitButton
              fullWidth
              loading={isPending}
              onClick={() => resolveIncidentMutation()}
            >
              Resolve
            </GradientSubmitButton>
          </Stack>
        </Stack>
      </IncidentModalBody>
    </ModalContainer>
  );
}
