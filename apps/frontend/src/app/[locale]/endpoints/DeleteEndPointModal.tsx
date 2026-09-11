import { Stack, Typography, useTheme } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { IEndpoint } from "@/@types/endpoint";
import { deleteEndpoint } from "@/api/endpoint";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import EndPointTypeChip from "@/components/EndpointTypeChip";
import { useCurrentTheme } from "@/hooks";
import { useScopedI18n } from "@/locales/client";

export default function DeleteEndPointModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IEndpoint }) {
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const t = useScopedI18n("endpoints");

  const { id, name, value, type, chatId } = data;

  const { mutate: deleteEndpointMutation, isPending } = useMutation({
    mutationFn: () => deleteEndpoint(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("EndPoint Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer
      {...props}
      onAfterDelete={deleteEndpointMutation}
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
        <Stack direction="row" spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: "bold"
            }}
          >
            {t("delete.field.type")}:
          </Typography>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary"
            }}
          >
            <EndPointTypeChip type={type} size="small" />
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: "bold"
            }}
          >
            {t("delete.field.value")}:
          </Typography>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              wordBreak: "break-word"
            }}
          >
            {type === "telegram" || type === "bale" ? chatId : value}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
