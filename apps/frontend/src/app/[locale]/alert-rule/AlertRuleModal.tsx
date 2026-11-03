import { useEffect, useState } from "react";

import { backdropClasses, Box, Fade, Modal, Paper } from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import { getAlertRuleCreateData } from "@/api/alertRule";
import ClientAPIForm from "@/components/AlertRule/Forms/ClientAPIForm";
import ElasticAlertRuleForm from "@/components/AlertRule/Forms/ElasticForm";
import GeneralAlertRuleForm from "@/components/AlertRule/Forms/GeneralAlertRuleForm";
import NotificationForm from "@/components/AlertRule/Forms/NotificationForm";
import SentryAlertRuleForm from "@/components/AlertRule/Forms/SentryForm";
import SplunkAlertRuleForm from "@/components/AlertRule/Forms/SplunkForm";
import VictoriaLogsAlertRuleForm from "@/components/AlertRule/Forms/VictoriaLogsForm";
import ZabbixAlertRuleForm from "@/components/AlertRule/Forms/ZabbixForm";
import type { ModalContainerProps } from "@/components/Modal/types";
import AlertRuleTypeSidebar from "@/components/AlertRule/AlertRuleTypeSidebar";
import { ALERT_RULE_VARIANTS, type AlertRuleType } from "@/utils/alertRuleUtils";

type AlertRuleModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<unknown>;
  onSubmit: () => void;
};

export default function AlertRuleModal({ open, onClose, onSubmit, data }: AlertRuleModalProps) {
  const [selectedAlertRuleType, setSelectedAlertRuleType] = useState<AlertRuleType>(
    Object.keys(ALERT_RULE_VARIANTS)[0] as AlertRuleType
  );

  useQuery({
    queryKey: ["alert-rule-create-data"],
    queryFn: () => getAlertRuleCreateData()
  });

  function handleShowForm() {
    switch (selectedAlertRuleType) {
      case "api":
        return (
          <ClientAPIForm
            onClose={onClose}
            data={data as CreateUpdateModal<IAlertRule>}
            onSubmit={onSubmit}
          />
        );
      case "pmm":
      case "grafana":
      case "prometheus":
        return (
          <GeneralAlertRuleForm
            data={data as CreateUpdateModal<IAlertRule>}
            onSubmit={onSubmit}
            onClose={onClose}
            type={selectedAlertRuleType}
          />
        );
      case "sentry":
        return (
          <SentryAlertRuleForm
            data={data as CreateUpdateModal<IAlertRule>}
            onSubmit={onSubmit}
            onClose={onClose}
          />
        );
      case "zabbix":
        return (
          <ZabbixAlertRuleForm
            data={data as CreateUpdateModal<IAlertRule>}
            onSubmit={onSubmit}
            onClose={onClose}
          />
        );
      case "splunk":
        return (
          <SplunkAlertRuleForm
            data={data as CreateUpdateModal<IAlertRule>}
            onSubmit={onSubmit}
            onClose={onClose}
          />
        );
      case "elastic":
        return (
          <ElasticAlertRuleForm
            data={data as CreateUpdateModal<IAlertRule>}
            onSubmit={onSubmit}
            onClose={onClose}
          />
        );
      case "victoria_logs":
        return (
          <VictoriaLogsAlertRuleForm
            data={data as CreateUpdateModal<IAlertRule>}
            onSubmit={onSubmit}
            onClose={onClose}
          />
        );
      case "notification":
        return (
          <NotificationForm
            data={data as CreateUpdateModal<IAlertRule>}
            onSubmit={onSubmit}
            onClose={onClose}
          />
        );
    }
  }

  useEffect(() => {
    if (data !== "NEW") {
      setSelectedAlertRuleType((data as { type: AlertRuleType }).type);
    }
  }, [data]);

  return (
    <Modal
      open={open}
      onClose={onClose}
      aria-labelledby="modal-modal-title"
      aria-describedby="modal-modal-description"
      sx={{
        [`& .${backdropClasses.root}`]: {
          backdropFilter: "blur(4px)"
        }
      }}
    >
      <Fade in={open}>
        <Box
          sx={{
            width: "100%",
            maxWidth: data === "NEW" ? "1000px" : "800px",
            height: "90vh",
            position: "absolute",
            top: "50%",
            left: "50%",
            transform: "translate(-50%,-50%)"
          }}
        >
          <Paper
            sx={{
              height: "100%",
              borderRadius: 4,
              boxShadow: "none",
              overflow: "hidden",
              display: "flex"
            }}
          >
            {data === "NEW" && (
              <AlertRuleTypeSidebar
                selectedType={selectedAlertRuleType}
                onSelect={setSelectedAlertRuleType}
              />
            )}
            <Box
              sx={{
                height: "100%",
                overflow: "auto",
                flex: 1
              }}
            >
              {handleShowForm()}
            </Box>
          </Paper>
        </Box>
      </Fade>
    </Modal>
  );
}
