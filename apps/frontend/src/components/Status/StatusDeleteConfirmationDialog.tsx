import { Typography, Chip, Stack, useTheme } from "@mui/material";

import { IStatusCard } from "@/@types/status";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import { formatTimeAgo } from "@/utils/general";

import { ModalContainerProps } from "../Modal/types";

interface StatusDeleteConfirmationDialogProps
  extends Pick<ModalContainerProps, "open" | "onClose"> {
  statusCard: IStatusCard | null;
  onConfirm: () => void;
  isLoading?: boolean;
}

export default function StatusDeleteConfirmationDialog({
  open,
  statusCard,
  onConfirm,
  onClose,
  isLoading = false
}: StatusDeleteConfirmationDialogProps) {
  const { palette } = useTheme();
  if (!statusCard) return null;

  return (
    <DeleteModalContainer
      open={open}
      onClose={onClose}
      onAfterDelete={onConfirm}
      isLoading={isLoading}
    >
      <Stack spacing={1}>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Name:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {statusCard.name}
          </Typography>
        </Stack>

        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Status:
          </Typography>
          {statusCard.criticalCount === 0 && statusCard.warningCount === 0 && (
            <Chip
              label="All Systems OK"
              size="small"
              sx={{
                backgroundColor: palette.success.main,
                color: "white",
                fontSize: "0.7rem"
              }}
            />
          )}
          {statusCard.criticalCount > 0 && (
            <Chip
              label={`${statusCard.criticalCount} Critical`}
              size="small"
              sx={{
                backgroundColor: palette.error.main,
                color: "white",
                fontSize: "0.7rem"
              }}
            />
          )}
          {statusCard.warningCount > 0 && (
            <Chip
              label={`${statusCard.warningCount} Warning`}
              size="small"
              sx={{
                backgroundColor: palette.warning.main,
                color: "white",
                fontSize: "0.7rem"
              }}
            />
          )}
        </Stack>

        {statusCard.tags && statusCard.tags.length > 0 && (
          <Stack direction="row" spacing={1}>
            <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
              Tags:
            </Typography>
            <Stack direction="row" spacing={1} alignItems="center">
              {statusCard.tags.slice(0, 4).map((tag, index) => (
                <Chip
                  key={index}
                  label={tag}
                  size="small"
                  variant="outlined"
                  sx={{
                    height: 18,
                    fontSize: "0.65rem",
                    borderColor: palette.grey[300],
                    color: palette.grey[600]
                  }}
                />
              ))}
              {statusCard.tags.length > 4 && (
                <Chip
                  label={`+${statusCard.tags.length - 4}`}
                  size="small"
                  variant="outlined"
                  sx={{
                    height: 18,
                    fontSize: "0.65rem",
                    borderColor: palette.grey[300],
                    color: palette.grey[600]
                  }}
                />
              )}
            </Stack>
          </Stack>
        )}
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Last updated:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {formatTimeAgo(statusCard.updatedAt)}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
