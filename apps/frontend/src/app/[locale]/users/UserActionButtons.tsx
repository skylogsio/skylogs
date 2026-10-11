"use client";

import { IconButton, Stack } from "@mui/material";
import type { SxProps, Theme } from "@mui/material";
import { HiKey, HiPencil, HiTrash } from "react-icons/hi";
import { mergeSx, mixPrimary, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";


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

  const buttonSx = (color: string): SxProps<Theme> =>
    mergeSx(
      {
        width: 36,
        height: 36,
        borderRadius: "10px",
        color,
        transition: "background-color 200ms ease, border-color 200ms ease"
      },
      pillSurfaceSx(),
      { "&:hover": { backgroundColor: "#E8E0D4", borderColor: mixPrimary(28) } },
      (muiTheme) =>
        muiTheme.applyStyles("dark", {
          "&:hover": {
            backgroundColor: "rgba(255, 255, 255, 0.13)",
            borderColor: mixPrimary(22)
          }
        })
    );

  return (
    <Stack direction="row" spacing={0.75} sx={{ justifyContent: "center" }}>
      {onEdit && (
        <IconButton onClick={onEdit} aria-label="Edit user" sx={buttonSx("primary.main")}>
          <HiPencil size="1.15rem" />
        </IconButton>
      )}
      {onChangePassword && (
        <IconButton
          onClick={onChangePassword}
          aria-label="Change password"
          sx={buttonSx("text.secondary")}
        >
          <HiKey size="1.1rem" />
        </IconButton>
      )}
      {onDelete && (
        <IconButton onClick={onDelete} aria-label="Delete user" sx={buttonSx("error.main")}>
          <HiTrash size="1.15rem" />
        </IconButton>
      )}
    </Stack>
  );
}
