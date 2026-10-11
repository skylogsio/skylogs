"use client";

import { IconButton, Stack } from "@mui/material";
import type { SxProps, Theme } from "@mui/material";
import { HiDownload, HiPencil, HiTrash } from "react-icons/hi";
import { mergeSx, mixPrimary, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";


type IncidentPolicyActionButtonsProps = {
  onEdit?: () => void;
  onExport?: () => void;
  onDelete?: () => void;
};

export default function IncidentPolicyActionButtons({
  onEdit,
  onExport,
  onDelete
}: IncidentPolicyActionButtonsProps) {

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

  if (!onEdit && !onExport && !onDelete) {
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
        <IconButton
          onClick={onEdit}
          aria-label="Edit incident policy"
          sx={buttonSx("primary.main")}
        >
          <HiPencil size="1.15rem" />
        </IconButton>
      )}
      {onExport && (
        <IconButton
          onClick={onExport}
          aria-label="Export incident policy YAML"
          sx={buttonSx("info.main")}
        >
          <HiDownload size="1.15rem" />
        </IconButton>
      )}
      {onDelete && (
        <IconButton
          onClick={onDelete}
          aria-label="Delete incident policy"
          sx={buttonSx("error.main")}
        >
          <HiTrash size="1.15rem" />
        </IconButton>
      )}
    </Stack>
  );
}
