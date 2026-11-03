"use client";

import { IconButton, Stack } from "@mui/material";
import type { SxProps, Theme } from "@mui/material";
import { HiEye, HiPencil, HiTrash } from "react-icons/hi";
import { mergeSx, mixPrimary, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";


type EndpointActionButtonsProps = {
  onView?: () => void;
  onEdit?: () => void;
  onDelete?: () => void;
};

export default function EndpointActionButtons({ onView, onEdit, onDelete }: EndpointActionButtonsProps) {

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

  if (!onView && !onEdit && !onDelete) {
    return <>—</>;
  }

  return (
    <Stack
      direction="row"
      spacing={0.75}
      sx={{ justifyContent: "center" }}
      onClick={(event) => event.stopPropagation()}
    >
      {onView && (
        <IconButton onClick={onView} aria-label="View details" sx={buttonSx("info.main")}>
          <HiEye size="1.15rem" />
        </IconButton>
      )}
      {onEdit && (
        <IconButton onClick={onEdit} aria-label="Edit endpoint" sx={buttonSx("primary.main")}>
          <HiPencil size="1.15rem" />
        </IconButton>
      )}
      {onDelete && (
        <IconButton onClick={onDelete} aria-label="Delete endpoint" sx={buttonSx("error.main")}>
          <HiTrash size="1.15rem" />
        </IconButton>
      )}
    </Stack>
  );
}
