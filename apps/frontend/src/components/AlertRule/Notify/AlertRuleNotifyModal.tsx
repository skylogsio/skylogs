import { useState } from "react";

import { Button } from "@mui/material";
import { HiOutlinePlusSm } from "react-icons/hi";

import type{ IAlertRule } from "@/@types/alertRule";
import AlertRuleNotifyManager from "@/components/AlertRule/Notify/AlertRuleNotifyManager";
import ModalContainer from "@/components/Modal";

interface NotifyModalProps {
  alertId: IAlertRule["id"] ;
  numberOfEndpoints: number;
  onClose: () => void;
}

export default function AlertRuleNotifyModal({ alertId, numberOfEndpoints, onClose }: NotifyModalProps) {
  const [open, setOpen] = useState(false);

  function handleClose() {
    onClose();
    setOpen(false);
  }

  return (
    <>
      <Button
        onClick={() => setOpen(true)}
        color={numberOfEndpoints > 0 ? "inherit" : "error"}
        variant="outlined"
        size="small"
        startIcon={numberOfEndpoints === 0 && <HiOutlinePlusSm size="1.3rem" />}
      >
        {numberOfEndpoints > 0 ? `View (${numberOfEndpoints})` : "Add"}
      </Button>
      <ModalContainer title="Notify" open={open} onClose={handleClose} disableEscapeKeyDown>
        <AlertRuleNotifyManager alertId={alertId} />
      </ModalContainer>
    </>
  );
}
