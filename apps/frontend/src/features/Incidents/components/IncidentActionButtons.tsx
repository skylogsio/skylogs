"use client";

import { alpha, IconButton, Stack, useTheme } from "@mui/material";
import { HiCheckCircle, HiPencil, HiTrash } from "react-icons/hi";

import { useCurrentTheme } from "@/hooks";

type IncidentActionButtonsProps = {
  onEdit?: () => void;
  onResolve?: () => void;
  onDelete?: () => void;
};

export default function IncidentActionButtons({
  onEdit,
  onResolve,
  onDelete
}: IncidentActionButtonsProps) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  const buttonSx = (color: string) => ({
    width: 36,
    height: 36,
    borderRadius: "10px",
    color,
    backgroundColor: isDark ? "rgba(255, 255, 255, 0.09)" : "#F1EBE1",
    border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`,
    transition: "background-color 200ms ease, border-color 200ms ease",
    "&:hover": {
      backgroundColor: isDark ? "rgba(255, 255, 255, 0.13)" : "#E8E0D4",
      borderColor: alpha(palette.primary.main, isDark ? 0.22 : 0.28)
    }
  });

  if (!onEdit && !onResolve && !onDelete) {
    return <>—</>;
  }

  return (
    <Stack
      direction="row"
      spacing={0.75}
      sx={{ justifyContent: "center" }}
      onClick={(event) => event.stopPropagation()}
    >
      {onEdit && (
        <IconButton onClick={onEdit} aria-label="Edit incident" sx={buttonSx(palette.primary.main)}>
          <HiPencil size="1.15rem" />
        </IconButton>
      )}
      {onResolve && (
        <IconButton
          onClick={onResolve}
          aria-label="Resolve incident"
          sx={buttonSx(palette.success.main)}
        >
          <HiCheckCircle size="1.2rem" />
        </IconButton>
      )}
      {onDelete && (
        <IconButton
          onClick={onDelete}
          aria-label="Delete incident"
          sx={buttonSx(palette.error.main)}
        >
          <HiTrash size="1.15rem" />
        </IconButton>
      )}
    </Stack>
  );
}
