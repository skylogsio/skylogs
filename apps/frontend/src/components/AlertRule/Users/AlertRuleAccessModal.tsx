import { useState } from "react";

import { IconButton, Tooltip } from "@mui/material";
import { FaUsersCog } from "react-icons/fa";

import type { IAlertRule } from "@/@types/alertRule";
import ModalContainer from "@/components/Modal";
import { actionButtonSx } from "@/components/ActionColumn";

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
      <Tooltip title="Access control" placement="bottom" arrow>
        <IconButton
          onClick={(event) => {
            event.stopPropagation();
            setOpen(true);
          }}
          aria-label="Access control"
          sx={actionButtonSx("primary.main")}
        >
          <FaUsersCog size="1.15rem" />
        </IconButton>
      </Tooltip>
      <ModalContainer title="Access Control" open={open} onClose={handleClose} disableEscapeKeyDown>
        <AlertRuleAccessManager alertId={alertId} onClose={handleClose} />
      </ModalContainer>
    </>
  );
}
