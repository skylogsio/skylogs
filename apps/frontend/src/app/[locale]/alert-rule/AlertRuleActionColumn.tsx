import { useEffect, useState } from "react";

import { alpha, Button, IconButton, Popover, Stack, Tooltip, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { BsCheck2, BsTerminalFill } from "react-icons/bs";
import { FaThumbtack, FaThumbtackSlash } from "react-icons/fa6";
import { HiDotsHorizontal } from "react-icons/hi";
import { IoNotifications, IoNotificationsOff } from "react-icons/io5";
import { RiTestTubeFill } from "react-icons/ri";

import type { IAlertRule } from "@/@types/alertRule";
import { pinAlertRule, silenceAlertRule, testAlertRule } from "@/api/alertRule";
import ActionColumn, { actionButtonSx, ActionColumnProps } from "@/components/ActionColumn";
import AlertRuleAccessModal from "@/components/AlertRule/Users/AlertRuleAccessModal";

interface AlertRuleActionColumnProps
  extends ActionColumnProps,
    Pick<IAlertRule, "hasActionAccess"> {
  rowId: string;
  isSilent: boolean;
  isPinned: boolean;
  type: IAlertRule["type"];
  apiToken?: string;
  refreshData?: () => void;
}

async function copyCurlCommand(apiToken: string, type: "api" | "notification") {
  const domain = window.location.origin;
  const endpoint = type === "api" ? "fire-alert" : "notification-alert";
  const curlCommand = `curl -X POST "${domain}/api/v1/${endpoint}" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer ${apiToken}" -d '{"instance": "test-instance", "description": "TEST API"}'`;
  await window.navigator.clipboard.writeText(curlCommand);
}

export default function AlertRuleActionColumn({
  rowId,
  isSilent,
  isPinned,
  type,
  apiToken,
  onEdit,
  onDelete,
  refreshData,
  hasActionAccess
}: AlertRuleActionColumnProps) {
  const [testConfirmationAnchorEl, setTestConfirmationAnchorEl] =
    useState<HTMLButtonElement | null>(null);
  const [showMoreAnchorEl, setShowMoreAnchorEl] = useState<HTMLButtonElement | null>(null);
  const [isSilentStatus, setIsSilentStatus] = useState<boolean>(Boolean(isSilent));
  const [isPinnedStatus, setIsPinnedStatus] = useState<boolean>(Boolean(isPinned));
  const [curlCopied, setCurlCopied] = useState(false);

  const handleCloseTestConfirmationPopover = () => {
    setTestConfirmationAnchorEl(null);
  };

  function handleTest(event: React.MouseEvent<HTMLButtonElement>) {
    setTestConfirmationAnchorEl(event.currentTarget);
  }

  async function handleCopyCurl() {
    await copyCurlCommand(apiToken!, type as "api" | "notification");
    setCurlCopied(true);
    setTimeout(() => setCurlCopied(false), 2000);
  }

  const openTestConfirmationPopover = Boolean(testConfirmationAnchorEl);
  const testConfirmationId = openTestConfirmationPopover ? "test-confirmation-popover" : undefined;

  const { mutate: silenceAlertRuleMutation, isPending: isSilencing } = useMutation({
    mutationFn: () => silenceAlertRule(rowId),
    onSuccess: (data) => {
      if (data.status) {
        setIsSilentStatus((prev) => !prev);
      }
    }
  });

  const { mutate: pinAlertRuleMutation, isPending: isPinning } = useMutation({
    mutationFn: () => pinAlertRule(rowId),
    onSuccess: (data) => {
      if (data.status) {
        refreshData?.();
        setIsPinnedStatus((prev) => !prev);
      }
    }
  });
  const { mutate: testAlertRuleMutation, isPending: isTesting } = useMutation({
    mutationFn: () => testAlertRule(rowId),
    onSuccess: (data) => {
      if (data.status) {
        handleCloseTestConfirmationPopover();
      }
    }
  });

  const handleShowMorePopoverOpen = (event: React.MouseEvent<HTMLButtonElement>) => {
    setShowMoreAnchorEl(event.currentTarget);
  };

  const handleShowMorePopoverClose = () => {
    setShowMoreAnchorEl(null);
  };

  const openShowMorePopover = Boolean(showMoreAnchorEl);
  const showMorePopoverId = openShowMorePopover ? "show-more-popover" : undefined;

  useEffect(() => {
    setIsSilentStatus(isSilent);
  }, [isSilent]);
  useEffect(() => {
    setIsPinnedStatus(isPinned);
  }, [isPinned]);

  return (
    <>
      <Stack
        direction="row"
        spacing={0.75}
        sx={{
          justifyContent: "center",
          alignItems: "center"
        }}
        onClick={(event) => event.stopPropagation()}
      >
        <Tooltip title="Test alert rule" placement="bottom" arrow>
          <IconButton
            onClick={(event) => {
              event.stopPropagation();
              handleTest(event);
            }}
            aria-label="Test alert rule"
            sx={actionButtonSx("#7C3AED", (theme) =>
              theme.applyStyles("dark", { color: "#A78BFA" })
            )}
          >
            <RiTestTubeFill size="1.15rem" />
          </IconButton>
        </Tooltip>

        <Tooltip
          title={isSilentStatus ? "Unsilence alert rule" : "Silence alert rule"}
          placement="bottom"
          arrow
        >
          <span>
            <IconButton
              onClick={(event) => {
                event.stopPropagation();
                silenceAlertRuleMutation();
              }}
              disabled={isSilencing}
              aria-label={isSilentStatus ? "Unsilence alert rule" : "Silence alert rule"}
              sx={actionButtonSx(isSilentStatus ? "text.secondary" : "warning.main")}
            >
              {isSilentStatus ? (
                <IoNotificationsOff size="1.2rem" />
              ) : (
                <IoNotifications size="1.2rem" />
              )}
            </IconButton>
          </span>
        </Tooltip>

        <Tooltip title="More actions" placement="bottom" arrow>
          <IconButton
            onClick={(event) => {
              event.stopPropagation();
              handleShowMorePopoverOpen(event);
            }}
            aria-label="More actions"
            sx={actionButtonSx("text.secondary")}
          >
            <HiDotsHorizontal size="1.2rem" />
          </IconButton>
        </Tooltip>
      </Stack>
      <Popover
        id={showMorePopoverId}
        open={openShowMorePopover}
        anchorEl={showMoreAnchorEl}
        onClose={handleShowMorePopoverClose}
        anchorOrigin={{
          vertical: "bottom",
          horizontal: "center"
        }}
        transformOrigin={{
          vertical: "top",
          horizontal: "center"
        }}
        slotProps={{
          paper: {
            sx: (theme) => ({
              mt: 0.5,
              p: 1,
              borderRadius: "12px",
              boxShadow: "0 10px 30px rgba(0, 0, 0, 0.12)",
              border: `1px solid ${alpha(theme.palette.primary.main, 0.2)}`,
              backgroundColor: theme.palette.background.paper,
              ...theme.applyStyles("dark", {
                boxShadow: "0 12px 32px rgba(0, 0, 0, 0.45)",
                border: `1px solid ${alpha(theme.palette.divider, 0.6)}`
              })
            })
          }
        }}
      >
        <Stack
          direction="row"
          spacing={0.75}
          sx={{
            alignItems: "center",
            flexWrap: "wrap",
            maxWidth: 320
          }}
          onClick={(event) => event.stopPropagation()}
        >
          {hasActionAccess && <ActionColumn onEdit={onEdit} onDelete={onDelete} />}
          <AlertRuleAccessModal alertId={rowId} />
          <Tooltip
            title={isPinnedStatus ? "Unpin alert rule" : "Pin alert rule"}
            placement="bottom"
            arrow
          >
            <span>
              <IconButton
                onClick={(event) => {
                  event.stopPropagation();
                  pinAlertRuleMutation();
                }}
                disabled={isPinning}
                aria-label={isPinnedStatus ? "Unpin alert rule" : "Pin alert rule"}
                sx={actionButtonSx(isPinnedStatus ? "primary.main" : "text.secondary")}
              >
                {isPinnedStatus ? (
                  <FaThumbtackSlash size="1.1rem" />
                ) : (
                  <FaThumbtack size="1.1rem" />
                )}
              </IconButton>
            </span>
          </Tooltip>
          {(type === "api" || type === "notification") && apiToken && (
            <Tooltip
              title={curlCopied ? "Copied!" : "Copy curl command"}
              placement="bottom"
              arrow
            >
              <IconButton
                onClick={(event) => {
                  event.stopPropagation();
                  handleCopyCurl();
                }}
                aria-label={curlCopied ? "Copied" : "Copy curl command"}
                sx={actionButtonSx(curlCopied ? "success.main" : "info.main")}
              >
                {curlCopied ? <BsCheck2 size="1.15rem" /> : <BsTerminalFill size="1.1rem" />}
              </IconButton>
            </Tooltip>
          )}
        </Stack>
      </Popover>
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
        slotProps={{
          paper: {
            sx: (theme) => ({
              mt: 0.5,
              borderRadius: "12px",
              boxShadow: "0 10px 30px rgba(0, 0, 0, 0.12)",
              border: `1px solid ${alpha(theme.palette.primary.main, 0.2)}`,
              backgroundColor: theme.palette.background.paper,
              ...theme.applyStyles("dark", {
                boxShadow: "0 12px 32px rgba(0, 0, 0, 0.45)",
                border: `1px solid ${alpha(theme.palette.divider, 0.6)}`
              })
            })
          }
        }}
      >
        <Stack
          spacing={3}
          sx={{
            padding: 2
          }}
        >
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
