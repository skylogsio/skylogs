import { useState } from "react";

import { IconButton } from "@mui/material";
import { FaUsersCog } from "react-icons/fa";

import type { IAlertRule } from "@/@types/alertRule";
import ModalContainer from "@/components/Modal";

import AlertRuleAccessManager from "./AlertRuleAccessManager";

interface AlertRuleAccessModalProps {
  alertId: IAlertRule["id"];
}

export default function AlertRuleAccessModal({ alertId }: AlertRuleAccessModalProps) {
  const [open, setOpen] = useState(false);

  function handleClose() {
    setOpen(false);
  }

  return (
    <>
      <IconButton
        onClick={() => setOpen(true)}
        sx={{
          color: "var(--mui-palette-primary-light)",
          backgroundColor:
            "color-mix(in srgb, var(--mui-palette-primary-light) 5%, transparent)"
        }}
      >
        <FaUsersCog size="1.3rem" />
      </IconButton>
      <ModalContainer title="Access Control" open={open} onClose={handleClose} disableEscapeKeyDown>
        <AlertRuleAccessManager alertId={alertId} onClose={handleClose} />
      </ModalContainer>
    </>
  );
}
