import { useEffect, useState } from "react";

import {
  backdropClasses,
  Box,
  Button,
  buttonClasses,
  ButtonGroup,
  Fade,
  Modal,
  Paper,
  useTheme
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import type { IAlertRule } from "@/@types/alertRule";
import type { CreateUpdateModal } from "@/@types/global";
import { getAlertRuleCreateData } from "@/api/alertRule";
import ClientAPIForm from "@/components/AlertRule/Forms/ClientAPIForm";
import GeneralAlertRuleForm from "@/components/AlertRule/Forms/GeneralAlertRuleForm";
import NotificationForm from "@/components/AlertRule/Forms/NotificationForm";
import SentryAlertRuleForm from "@/components/AlertRule/Forms/SentryForm";
import SplunkAlertRuleForm from "@/components/AlertRule/Forms/SplunkForm";
import ZabbixAlertRuleForm from "@/components/AlertRule/Forms/ZabbixForm";
import type { ModalContainerProps } from "@/components/Modal/types";
import { ALERT_RULE_VARIANTS, type AlertRuleType } from "@/utils/alertRuleUtils";

type AlertRuleModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<unknown>;
  onSubmit: () => void;
};

export default function AlertRuleModal({ open, onClose, onSubmit, data }: AlertRuleModalProps) {
  const { palette } = useTheme();
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

  function renderAlertRuleSideBar() {
    return Object.entries(ALERT_RULE_VARIANTS).map(
      ([key, value]) =>
        key !== "health" && (
          <Button
            startIcon={<value.Icon size={value.defaultSize} color={value.defaultColor} />}
            key={key}
            onClick={() => setSelectedAlertRuleType(key as AlertRuleType)}
            sx={{
              paddingX: 3,
              paddingY: 1.7,
              justifyContent: "flex-start",
              textTransform: "capitalize",
              borderColor: `${palette.divider} !important`,
              color: key === selectedAlertRuleType ? palette.primary.main : palette.secondary.dark,
              fontSize: "1rem",
              [`& .${buttonClasses.icon}`]: {
                width: 24,
                height: 24,
                marginRight: 2,
                "& svg": {
                  width: "inherit",
                  height: "inherit"
                }
              },
              "&::before": {
                content: "''",
                display: key === selectedAlertRuleType ? "initial" : "none",
                position: "absolute",
                top: 0,
                left: 0,
                width: "10px",
                height: "100%",
                transform: "translateX(-1rem)",
                backgroundColor: palette.primary.main
              }
            }}
          >
            {value.label}
          </Button>
        )
    );
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
          width="100%"
          maxWidth={data === "NEW" ? "1000px" : "800px"}
          maxHeight="90vh"
          sx={{
            position: "absolute",
            top: "50%",
            left: "50%",
            transform: "translate(-50%,-50%)"
          }}
        >
          <Paper
            sx={{ borderRadius: "0.7rem", boxShadow: "none", overflow: "hidden", display: "flex" }}
          >
            {data === "NEW" && (
              <ButtonGroup
                variant="text"
                orientation="vertical"
                sx={{ paddingX: "1rem", paddingY: 1, borderRight: `1px solid ${palette.divider}` }}
              >
                {renderAlertRuleSideBar()}
              </ButtonGroup>
            )}
            <Box maxHeight="90vh" overflow="auto" flex={1}>
              {handleShowForm()}
            </Box>
          </Paper>
        </Box>
      </Fade>
    </Modal>
  );
}
