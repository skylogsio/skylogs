import { useState } from "react";

import { alpha, Box, Button, Stack, type SxProps, type Theme } from "@mui/material";
import { HiOutlinePlusSm } from "react-icons/hi";

import type { IAlertRule } from "@/@types/alertRule";
import AlertRuleNotifyManager from "@/components/AlertRule/Notify/AlertRuleNotifyManager";
import ModalContainer from "@/components/Modal";

interface NotifyModalProps {
  alertId: IAlertRule["id"];
  numberOfEndpoints: number;
  onClose: () => void;
}

const addButtonSx: SxProps<Theme> = (theme) => ({
  border: `1px dashed ${alpha(theme.palette.text.secondary, 0.4)}`,
  color: theme.palette.text.secondary,
  backgroundColor: "transparent",
  fontWeight: 600,
  fontSize: "0.75rem",
  textTransform: "capitalize",
  borderRadius: "8px",
  px: 1.25,
  minHeight: 28,
  height: 28,
  transition: "all 150ms ease",
  "& .MuiButton-startIcon": {
    mr: 0.5,
    color: "inherit"
  },
  "&:hover": {
    border: `1px dashed ${alpha(theme.palette.text.primary, 0.45)}`,
    backgroundColor: alpha(theme.palette.text.primary, 0.05),
    color: theme.palette.text.primary
  }
});

const viewButtonSx: SxProps<Theme> = [
  {
    border: "1px solid color-mix(in srgb, var(--mui-palette-primary-main) 22%, transparent)",
    color: "primary.main",
    backgroundColor: "color-mix(in srgb, var(--mui-palette-primary-main) 3%, transparent)",
    fontWeight: 600,
    fontSize: "0.75rem",
    textTransform: "none",
    borderRadius: "8px",
    px: 1.25,
    minHeight: 28,
    height: 28,
    boxShadow: "none !important",
    transition: "background-color 150ms ease, border-color 150ms ease, transform 150ms ease",
    "&:hover": {
      backgroundColor: "color-mix(in srgb, var(--mui-palette-primary-main) 7%, transparent)",
      borderColor: "color-mix(in srgb, var(--mui-palette-primary-main) 38%, transparent)",
      boxShadow: "none !important"
    },
    "&:active": {
      transform: "scale(0.97)"
    }
  },
  (muiTheme: Theme) =>
    muiTheme.applyStyles("dark", {
      backgroundColor: "color-mix(in srgb, var(--mui-palette-primary-main) 8%, transparent)",
      borderColor: "color-mix(in srgb, var(--mui-palette-primary-main) 24%, transparent)",
      "&:hover": {
        backgroundColor: "color-mix(in srgb, var(--mui-palette-primary-main) 14%, transparent)",
        borderColor: "color-mix(in srgb, var(--mui-palette-primary-main) 42%, transparent)"
      }
    })
];

export default function AlertRuleNotifyModal({
  alertId,
  numberOfEndpoints,
  onClose
}: NotifyModalProps) {
  const [open, setOpen] = useState(false);

  function handleClose() {
    onClose();
    setOpen(false);
  }

  return (
    <>
      <Button
        onClick={() => setOpen(true)}
        color="inherit"
        size="small"
        startIcon={numberOfEndpoints === 0 ? <HiOutlinePlusSm size={15} /> : null}
        sx={numberOfEndpoints === 0 ? addButtonSx : viewButtonSx}
      >
        {numberOfEndpoints > 0 ? (
          <Stack direction="row" spacing={0.6} sx={{ alignItems: "center" }}>
            <span>View</span>
            <Box
              component="span"
              sx={{
                display: "inline-flex",
                alignItems: "center",
                justifyContent: "center",
                minWidth: 18,
                height: 18,
                px: 0.6,
                borderRadius: "999px",
                fontSize: "0.68rem",
                fontWeight: 700,
                backgroundColor:
                  "color-mix(in srgb, var(--mui-palette-primary-main) 10%, transparent)",
                color: "primary.main",
                lineHeight: 1
              }}
            >
              {numberOfEndpoints}
            </Box>
          </Stack>
        ) : (
          "Add"
        )}
      </Button>
      <ModalContainer title="Notify" open={open} onClose={handleClose} disableEscapeKeyDown>
        <AlertRuleNotifyManager alertId={alertId} />
      </ModalContainer>
    </>
  );
}
