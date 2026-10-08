"use client";

import { useParams, usePathname, useRouter, useSearchParams } from "next/navigation";
import React, { useState } from "react";

import { alpha, Button, IconButton, Popover, Stack, Typography, useTheme } from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { BsFillClipboard2Fill } from "react-icons/bs";
import { HiPencil, HiTrash } from "react-icons/hi";
import { IoNotifications, IoNotificationsOff } from "react-icons/io5";
import { RiTestTubeFill } from "react-icons/ri";
import { BsCheck2, BsTerminalFill } from "react-icons/bs";

import type { IAlertRule } from "@/@types/alertRule";
import { getAlertRuleById, silenceAlertRule, testAlertRule } from "@/api/alertRule";
import AlertRuleModal from "@/app/[locale]/alert-rule/AlertRuleModal";
import DeleteAlertRuleModal from "@/app/[locale]/alert-rule/DeleteAlertRuleModal";
import AdvanceSection from "@/components/AlertRule/advance/AdvanceSection";
import AlertRuleDetailsTab from "@/components/AlertRule/Details/AlertRuleDetailsTab";
import AlertRuleSummaryCard from "@/components/AlertRule/Details/AlertRuleSummaryCard";
import AlertRuleTabSwitch, {
  ALERT_RULE_TABS,
  type AlertRuleTabType
} from "@/components/AlertRule/Details/AlertRuleTabSwitch";
import AlertRuleFiredInstances from "@/components/AlertRule/FiredInstances/AlertRuleFiredInstances";
import AlertRuleHistory from "@/components/AlertRule/History/AlertRuleHistory";
import AlertRuleNotifyManager from "@/components/AlertRule/Notify/AlertRuleNotifyManager";
import AlertRuleAccessManager from "@/components/AlertRule/Users/AlertRuleAccessManager";

const TABS = ALERT_RULE_TABS;
type TabType = AlertRuleTabType;

const READONLY_TABS: TabType[] = ["details", "fire", "history"];
const DEFAULT_TAB: TabType = "details";
const READONLY_DEFAULT_TAB: TabType = "details";

function isTabType(value: string | null): value is TabType {
  return TABS.includes(value as TabType);
}

export default function ViewAlertRule() {
  const { alertId } = useParams<{ alertId: string }>();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const { palette, spacing } = useTheme();
  const [testConfirmationAnchorEl, setTestConfirmationAnchorEl] =
    useState<HTMLButtonElement | null>(null);
  const [currentOpenModal, setCurrentOpenModal] = useState<"DELETE" | "EDIT" | null>(null);
  const [curlCopied, setCurlCopied] = useState(false);
  const [tokenCopied, setTokenCopied] = useState(false);

  const { data, refetch } = useQuery({
    queryKey: ["view-alert-rule", alertId],
    queryFn: () => getAlertRuleById(alertId),
    enabled: Boolean(alertId),
    refetchInterval: 10 * 1000
  });
  console.log("🚀 ~ ViewAlertRule ~ data:", data);

  const isReadonly = data?.accessLevel === "readonly";
  const availableTabs = isReadonly ? READONLY_TABS : TABS;
  const defaultTab = isReadonly ? READONLY_DEFAULT_TAB : DEFAULT_TAB;
  const tabParam = searchParams.get("tab");
  const currentTab: TabType =
    isTabType(tabParam) && availableTabs.includes(tabParam) ? tabParam : defaultTab;

  function setCurrentTab(tab: TabType) {
    const params = new URLSearchParams(searchParams.toString());
    params.set("tab", tab);
    router.push(`${pathname}?${params.toString()}`);
  }

  function handleAfterDelete() {
    router.push("/alert-rule");
  }

  function handleTest(event: React.MouseEvent<HTMLButtonElement>) {
    setTestConfirmationAnchorEl(event.currentTarget);
  }

  const handleCloseTestConfirmationPopover = () => {
    setTestConfirmationAnchorEl(null);
  };
  const openTestConfirmationPopover = Boolean(testConfirmationAnchorEl);
  const testConfirmationId = openTestConfirmationPopover ? "test-confirmation-popover" : undefined;

  const { mutate: silenceAlertRuleMutation, isPending: isSilencing } = useMutation({
    mutationFn: () => silenceAlertRule(alertId),
    onSuccess: (data) => {
      if (data.status) {
        queryClient.setQueryData(["view-alert-rule", alertId], (oldData: IAlertRule) => ({
          ...oldData,
          is_silent: !oldData.is_silent
        }));
      }
    }
  });

  async function handleCopyApiTokenToClipboard() {
    try {
      await window.navigator.clipboard.writeText(data!.apiToken!);
      setTokenCopied(true);
      setTimeout(() => setTokenCopied(false), 2000);
    } catch (err) {
      console.error("Unable to copy to clipboard.", err);
      alert("Copy to clipboard failed.");
    }
  }

  async function handleCopyCurlCommand() {
    try {
      const domain = window.location.origin;
      const endpoint = data!.type === "api" ? "fire-alert" : "notification-alert";
      const curlCommand = `curl -X POST "${domain}/api/v1/${endpoint}" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer ${data!.apiToken!}" -d '{"instance": "test-instance", "description": "TEST API"}'`;
      await window.navigator.clipboard.writeText(curlCommand);
      setCurlCopied(true);
      setTimeout(() => setCurlCopied(false), 2000);
    } catch (err) {
      console.error("Unable to copy to clipboard.", err);
      alert("Copy to clipboard failed.");
    }
  }

  const { mutate: testAlertRuleMutation, isPending: isTesting } = useMutation({
    mutationFn: () => testAlertRule(alertId),
    onSuccess: (data) => {
      if (data.status) {
        handleCloseTestConfirmationPopover();
      }
    }
  });

  const activeTab: TabType =
    currentTab === "fire" && data?.status_label !== "critical" ? defaultTab : currentTab;

  const visibleTabs = availableTabs.filter(
    (tab) => tab !== "fire" || data?.status_label === "critical"
  );

  function renderActionButtons() {
    return (
      <Stack direction="row-reverse" spacing={1} sx={{ flexWrap: "wrap", rowGap: 1 }}>
        {data!.hasActionAccess && (
          <>
            <Button
              startIcon={<HiTrash />}
              onClick={() => setCurrentOpenModal("DELETE")}
              sx={{
                textTransform: "capitalize !important",
                color: palette.error.main,
                backgroundColor: alpha(palette.error.main, 0.05),
                paddingX: 2
              }}
            >
              Delete
            </Button>
            <Button
              startIcon={<HiPencil />}
              onClick={() => setCurrentOpenModal("EDIT")}
              sx={{
                textTransform: "capitalize !important",
                color: palette.info.light,
                backgroundColor: alpha(palette.info.light, 0.05),
                paddingX: 2
              }}
            >
              Edit
            </Button>
          </>
        )}
        <Button
          startIcon={
            data!.is_silent ? (
              <IoNotificationsOff size="1.4rem" />
            ) : (
              <IoNotifications size="1.4rem" />
            )
          }
          disabled={isSilencing}
          onClick={() => silenceAlertRuleMutation()}
          sx={{
            textTransform: "capitalize !important",
            color: palette.warning.main,
            backgroundColor: alpha(palette.warning.main, 0.05),
            paddingX: 2
          }}
        >
          {data!.is_silent ? "Unsilent" : "Silent"}
        </Button>
        <Button
          onClick={handleTest}
          startIcon={<RiTestTubeFill size="1.4rem" />}
          sx={{
            textTransform: "capitalize !important",
            color: palette.primary.light,
            backgroundColor: alpha(palette.primary.light, 0.05),
            paddingX: 2
          }}
        >
          Test
        </Button>
        {(data!.type === "api" || data!.type === "notification") && data!.apiToken && (
          <Button
            onClick={handleCopyCurlCommand}
            startIcon={curlCopied ? <BsCheck2 size="1.4rem" /> : <BsTerminalFill size="1.4rem" />}
            sx={{
              textTransform: "capitalize !important",
              color: curlCopied ? palette.success.main : palette.info.main,
              backgroundColor: curlCopied
                ? alpha(palette.success.main, 0.1)
                : alpha(palette.info.main, 0.05),
              paddingX: 2,
              transition: "all 0.2s ease-in-out"
            }}
          >
            {curlCopied ? "Copied!" : "Copy Curl"}
          </Button>
        )}
      </Stack>
    );
  }

  function renderApiToken() {
    if (!data!.apiToken) {
      return null;
    }
    return (
      <Stack
        direction="row"
        spacing={1}
        sx={{
          alignItems: "center",
          alignSelf: "flex-start",
          bgcolor: alpha(palette.secondary.light, 0.1),
          borderRadius: 2,
          paddingY: 0.5,
          paddingLeft: 1,
          paddingRight: 0.5,
          border: 1,
          borderColor: alpha(palette.secondary.light, 0.4)
        }}
      >
        <Typography
          variant="caption"
          color={tokenCopied ? palette.success.main : palette.secondary.main}
          sx={{
            maxWidth: "300px",
            textWrap: "nowrap",
            overflow: "hidden",
            textOverflow: "ellipsis",
            transition: "color 0.2s ease-in-out"
          }}
        >
          {tokenCopied ? "Copied to clipboard!" : data!.apiToken}
        </Typography>
        <IconButton size="small" onClick={() => handleCopyApiTokenToClipboard()}>
          {tokenCopied ? (
            <BsCheck2 size="1rem" color={palette.success.main} />
          ) : (
            <BsFillClipboard2Fill size="1rem" color={palette.secondary.main} />
          )}
        </IconButton>
      </Stack>
    );
  }

  function renderDetailsTab() {
    return (
      <Stack spacing={3}>
        {!isReadonly && renderActionButtons()}
        {renderApiToken()}
        <AlertRuleDetailsTab alert={data!} />
      </Stack>
    );
  }

  function renderSections() {
    switch (activeTab) {
      case "details":
        return renderDetailsTab();
      case "users":
        return <AlertRuleAccessManager alertId={alertId} />;
      case "history":
        return <AlertRuleHistory alertId={alertId} type={data!.type} />;
      case "notify":
        return <AlertRuleNotifyManager alertId={alertId} />;
      case "fire":
        return <AlertRuleFiredInstances alertId={alertId} type={data!.type} />;
      case "advance":
        return <AdvanceSection type={data!.type} readOnly={!data!.hasActionAccess} />;
      default:
        return null;
    }
  }

  if (!data) {
    return null;
  }

  function handleRefreshData() {
    refetch();
  }

  return (
    <>
      <Stack spacing={1} sx={{ width: "100%", alignItems: "center" }}>
        <Stack sx={{ width: "100%", bgcolor: "transparent", padding: 1 }}>
          <AlertRuleSummaryCard
            alert={data}
            isReadonly={isReadonly}
            alertId={alertId}
            onAfterResolve={handleRefreshData}
          />
        </Stack>
        <Stack
          direction="row"
          sx={{
            minWidth: "100%",
            marginRight: "auto",
            marginTop: `${spacing(1)}!important`
          }}
        >
          <AlertRuleTabSwitch tabs={visibleTabs} activeTab={activeTab} onChange={setCurrentTab} />
        </Stack>
        <Stack
          sx={{ width: "100%", bgcolor: palette.background.paper, borderRadius: 3, padding: 3 }}
        >
          {renderSections()}
        </Stack>
      </Stack>
      {currentOpenModal === "DELETE" && (
        <DeleteAlertRuleModal
          open={currentOpenModal === "DELETE"}
          onClose={() => setCurrentOpenModal(null)}
          onAfterDelete={handleAfterDelete}
          data={data}
        />
      )}
      {currentOpenModal === "EDIT" && (
        <AlertRuleModal
          open={currentOpenModal === "EDIT"}
          onClose={() => setCurrentOpenModal(null)}
          data={data}
          onSubmit={handleRefreshData}
        />
      )}
      <Popover
        id={testConfirmationId}
        open={openTestConfirmationPopover}
        anchorEl={testConfirmationAnchorEl}
        onClose={handleCloseTestConfirmationPopover}
        anchorOrigin={{
          vertical: "bottom",
          horizontal: "center"
        }}
        transformOrigin={{
          vertical: "top",
          horizontal: "center"
        }}
      >
        <Stack spacing={3} sx={{ padding: 2 }}>
          <Typography variant="subtitle1">
            Are you sure about <strong>Testing</strong> this Alert?
          </Typography>
          <Stack direction="row-reverse" spacing={1}>
            <Button
              size="small"
              variant="contained"
              onClick={() => testAlertRuleMutation()}
              disabled={isTesting}
              sx={{ flex: 1 }}
            >
              Test
            </Button>
            <Button
              onClick={handleCloseTestConfirmationPopover}
              size="small"
              variant="outlined"
              disabled={isTesting}
              sx={{ flex: 1 }}
            >
              Cancel
            </Button>
          </Stack>
        </Stack>
      </Popover>
    </>
  );
}
