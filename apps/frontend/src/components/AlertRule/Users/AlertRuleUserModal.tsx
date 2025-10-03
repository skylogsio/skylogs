import { useState } from "react";

import { alpha, IconButton } from "@mui/material";
import { FaUsers } from "react-icons/fa";

import type { IAlertRule } from "@/@types/alertRule";
import ModalContainer from "@/components/Modal";

import AlertRuleUserManager from "./AlertRuleUserManager";

interface AlertRuleUserModalProps {
  alertId: IAlertRule["id"];
}

export default function AlertRuleUserModal({ alertId }: AlertRuleUserModalProps) {
  const [open, setOpen] = useState(false);

  function handleClose() {
    setOpen(false);
  }

  return (
    <>
      <IconButton
        onClick={() => setOpen(true)}
        sx={({ palette }) => ({
          color: palette.primary.light,
          backgroundColor: alpha(palette.primary.light, 0.05)
        })}
      >
        <FaUsers size="1.3rem" />
      </IconButton>
      <ModalContainer title="Users" open={open} onClose={handleClose} disableEscapeKeyDown>
        <AlertRuleUserManager alertId={alertId} onClose={handleClose} />
      </ModalContainer>
    </>
  );
}
