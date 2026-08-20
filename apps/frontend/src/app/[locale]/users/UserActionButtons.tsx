"use client";

import { alpha, IconButton, Stack, useTheme } from "@mui/material";
import { HiKey, HiPencil, HiTrash } from "react-icons/hi";

import { useCurrentTheme } from "@/hooks";

type UserActionButtonsProps = {
  onEdit?: () => void;
  onChangePassword?: () => void;
  onDelete?: () => void;
};

export default function UserActionButtons({
  onEdit,
  onChangePassword,
  onDelete
}: UserActionButtonsProps) {
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

  return (
    <Stack direction="row" spacing={0.75} sx={{ justifyContent: "center" }}>
      {onEdit && (
        <IconButton onClick={onEdit} aria-label="Edit user" sx={buttonSx(palette.primary.main)}>
          <HiPencil size="1.15rem" />
        </IconButton>
      )}
      {onChangePassword && (
        <IconButton
          onClick={onChangePassword}
          aria-label="Change password"
          sx={buttonSx(palette.text.secondary)}
        >
          <HiKey size="1.1rem" />
        </IconButton>
      )}
      {onDelete && (
        <IconButton onClick={onDelete} aria-label="Delete user" sx={buttonSx(palette.error.main)}>
          <HiTrash size="1.15rem" />
        </IconButton>
      )}
    </Stack>
  );
}
