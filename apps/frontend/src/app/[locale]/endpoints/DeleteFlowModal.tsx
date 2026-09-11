import { Box, Stack, Typography, useTheme } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { IFlow } from "@/@types/flow";
import { deleteFlow } from "@/api/flow";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import FlowStepChip from "@/components/Endpoint/FlowStepChip";
import { useCurrentTheme } from "@/hooks";
import { useScopedI18n } from "@/locales/client";

export default function DeleteFlowModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IFlow }) {
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const t = useScopedI18n("endpoints");

  const { id, name, steps } = data;

  const { mutate: deleteFlowMutation, isPending } = useMutation({
    mutationFn: () => deleteFlow(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("Endpoint Flow Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer
      {...props}
      onAfterDelete={deleteFlowMutation}
      isLoading={isPending}
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <Stack spacing={1}>
        <Stack direction="row" spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: "bold"
            }}
          >
            {t("delete.field.name")}:
          </Typography>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary"
            }}
          >
            {name}
          </Typography>
        </Stack>

        <Stack spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: "bold"
            }}
          >
            {t("delete.field.steps")}:
          </Typography>
          <Box sx={{ display: "flex", flexWrap: "wrap", gap: 1 }}>
            {steps.map((step, index) => (
              <FlowStepChip
                key={index}
                type={step.type}
                duration={step.duration}
                timeUnit={step.timeUnit}
              />
            ))}
          </Box>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
