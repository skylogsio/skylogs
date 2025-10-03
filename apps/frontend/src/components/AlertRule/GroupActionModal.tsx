import React, { useState } from "react";

import {
  Alert,
  alpha,
  Box,
  Button,
  Chip,
  Divider,
  MenuItem,
  Stack,
  TextField,
  Typography,
  useTheme
} from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { FaUserCog } from "react-icons/fa";
import { IoNotifications, IoNotificationsOff } from "react-icons/io5";
import { toast } from "react-toastify";

import {
  addUserAndNotifyToAlertRules,
  getAlertRuleCreateData,
  silentAlertRules,
  unsilentAlertRules
} from "@/api/alertRule";
import ModalContainer from "@/components/Modal";
import { ModalContainerProps } from "@/components/Modal/types";

interface GroupActionModalProps extends Pick<ModalContainerProps, "open" | "onClose"> {
  currentFilters?: Record<string, unknown>;
}

export type GroupActionType = "silent" | "unsilent" | "add_user_notify";

export interface GroupActionData {
  actionType: GroupActionType;
  selectedEndpointIds: string[];
  selectedUsers: string[];
  currentFilters: Record<string, unknown>;
}

export default function GroupActionModal({
  open,
  onClose,
  currentFilters = {}
}: GroupActionModalProps) {
  const { palette } = useTheme();
  const [endpointIds, setEndpointIds] = useState<string[]>([]);
  const [userIds, setUserIds] = useState<string[]>([]);
  const [selectedAction, setSelectedAction] = useState<GroupActionType | null>(null);

  const { data } = useQuery({
    queryKey: ["alert-rule-create-data"],
    queryFn: () => getAlertRuleCreateData()
  });

  const { mutate: silentAlertRulesMutation, isPending: isSilenting } = useMutation({
    mutationFn: () => silentAlertRules(currentFilters),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Alert Rules Silenced Successfully!");
        onClose?.();
      }
    }
  });

  const { mutate: unsilentAlertRulesMutation, isPending: isUnsilenting } = useMutation({
    mutationFn: () => unsilentAlertRules(currentFilters),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Alert Rules Unsilenced Successfully!");
        onClose?.();
      }
    }
  });

  const { mutate: addUserAndNotifyToAlertRulesMutation, isPending: isAddingUserAndNotify } =
    useMutation({
      mutationFn: () => addUserAndNotifyToAlertRules(currentFilters, { endpointIds, userIds }),
      onSuccess: (data) => {
        if (data.status) {
          toast.success("Notifications and Users Added to Alert Rules Successfully!");
          onClose?.();
        }
      }
    });

  const handleRemoveEndpointChip = (endpointId: string) => {
    setEndpointIds((prev) => prev.filter((id) => id !== endpointId));
  };

  const renderEndpointChips = (selectedIds: unknown) => {
    const selected =
      data?.endpoints.filter((endpoint) => (selectedIds as string[]).includes(endpoint.id)) ?? [];
    return (
      <Stack
        gap={1}
        direction="row"
        flexWrap="wrap"
        justifyContent="flex-start"
        sx={{ float: "left" }}
        onMouseDown={(event) => event.stopPropagation()}
      >
        {selected.map((endpoint) => (
          <Chip
            key={endpoint.id}
            label={endpoint.name}
            size="small"
            onDelete={() => handleRemoveEndpointChip(endpoint.id)}
          />
        ))}
      </Stack>
    );
  };

  const handleRemoveUserChip = (userId: string) => {
    setUserIds((prev) => prev.filter((id) => id !== userId));
  };

  const renderUserChips = (selectedIds: unknown) => {
    const selected =
      data?.users.filter((user) => (selectedIds as string[]).includes(user.id)) ?? [];
    return (
      <Stack
        gap={1}
        direction="row"
        flexWrap="wrap"
        justifyContent="flex-start"
        sx={{ float: "left" }}
        onMouseDown={(event) => event.stopPropagation()}
      >
        {selected.map((user) => (
          <Chip
            key={user.id}
            label={user.name}
            size="small"
            onDelete={() => handleRemoveUserChip(user.id)}
          />
        ))}
      </Stack>
    );
  };

  const handleActionSelect = (actionType: GroupActionType) => {
    setSelectedAction(actionType);
  };

  const renderFilters = () => {
    const filterEntries = Object.entries(currentFilters).filter(
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
      ([_, value]) =>
        value !== undefined &&
        value !== null &&
        value !== "" &&
        !(Array.isArray(value) && value.length === 0)
    );

    if (filterEntries.length === 0) {
      return (
        <Typography color="text.secondary" variant="body2">
          No filters applied - All alert rules will be affected
        </Typography>
      );
    }

    return (
      <Stack spacing={1}>
        {filterEntries.map(([key, value]) => (
          <Stack key={key} direction="row" spacing={1} alignItems="center">
            <Typography variant="body2" textTransform="capitalize" fontWeight="bold" minWidth={120}>
              {key}:
            </Typography>
            <Typography
              variant="body2"
              textTransform={Array.isArray(value) ? "none" : "capitalize"}
            >
              {Array.isArray(value) ? value.join(", ") : String(value)}
            </Typography>
          </Stack>
        ))}
      </Stack>
    );
  };

  const handleSubmit = async () => {
    if (!selectedAction) {
      toast.error("Please select an action first.");
      return;
    }

    if (selectedAction === "add_user_notify" && endpointIds.length === 0 && userIds.length === 0) {
      toast.error("Please select at least one endpoint or user for notification.");
      return;
    }

    if (selectedAction === "silent") {
      silentAlertRulesMutation();
    } else if (selectedAction === "unsilent") {
      unsilentAlertRulesMutation();
    } else if (selectedAction === "add_user_notify") {
      addUserAndNotifyToAlertRulesMutation();
    }
  };

  const handleCloseModal = () => {
    setSelectedAction(null);
    setEndpointIds([]);
    setUserIds([]);
    onClose?.();
  };

  const isLoading = isSilenting || isUnsilenting || isAddingUserAndNotify;

  return (
    <ModalContainer
      title="Group Actions"
      open={open}
      onClose={handleCloseModal}
      disableEscapeKeyDown
    >
      <Stack spacing={3}>
        <Stack spacing={2}>
          <Typography variant="subtitle1" fontWeight="bold">
            Applied Filters:
          </Typography>
          <Box
            sx={{
              p: 2,
              backgroundColor: alpha(palette.info.main, 0.05),
              borderRadius: 1,
              border: `1px solid ${alpha(palette.info.main, 0.2)}`
            }}
          >
            {renderFilters()}
          </Box>
        </Stack>

        <Divider />

        <Stack spacing={2}>
          <Typography variant="subtitle1" fontWeight="bold">
            Select Action:
          </Typography>
          <Stack direction="row" spacing={1} flexWrap="wrap">
            <Button
              startIcon={<IoNotificationsOff size="1.4rem" />}
              onClick={() => handleActionSelect("silent")}
              variant={selectedAction === "silent" ? "contained" : "outlined"}
              sx={{
                textTransform: "capitalize !important",
                color: selectedAction === "silent" ? "white" : palette.warning.main,
                backgroundColor:
                  selectedAction === "silent"
                    ? palette.warning.main
                    : alpha(palette.warning.main, 0.05),
                border: "none",
                paddingX: 2
              }}
            >
              Silent
            </Button>
            <Button
              startIcon={<IoNotifications size="1.4rem" />}
              onClick={() => handleActionSelect("unsilent")}
              variant={selectedAction === "unsilent" ? "contained" : "outlined"}
              sx={{
                textTransform: "capitalize !important",
                color: selectedAction === "unsilent" ? "white" : palette.warning.main,
                backgroundColor:
                  selectedAction === "unsilent"
                    ? palette.warning.main
                    : alpha(palette.warning.main, 0.05),
                border: "none",
                paddingX: 2
              }}
            >
              Unsilent
            </Button>
            <Button
              startIcon={<FaUserCog size="1.4rem" />}
              onClick={() => handleActionSelect("add_user_notify")}
              variant={selectedAction === "add_user_notify" ? "contained" : "outlined"}
              sx={{
                textTransform: "capitalize !important",
                color: selectedAction === "add_user_notify" ? "white" : palette.success.main,
                backgroundColor:
                  selectedAction === "add_user_notify"
                    ? palette.success.main
                    : alpha(palette.success.main, 0.05),
                border: "none",
                paddingX: 2
              }}
            >
              Add User/Notify
            </Button>
          </Stack>
        </Stack>

        {selectedAction && (
          <>
            <Divider />
            <Stack spacing={2}>
              {selectedAction === "add_user_notify" && (
                <Stack spacing={2}>
                  <Typography variant="subtitle2" fontWeight="bold">
                    User And Notification Settings:
                  </Typography>
                  <TextField
                    select
                    label="Endpoints"
                    variant="filled"
                    value={endpointIds}
                    onChange={(event) => setEndpointIds(event.target.value as unknown as string[])}
                    slotProps={{
                      select: {
                        multiple: true,
                        renderValue: renderEndpointChips
                      }
                    }}
                  >
                    {data?.endpoints.map((endpoint) => (
                      <MenuItem key={endpoint.id} value={endpoint.id}>
                        {endpoint.name}
                      </MenuItem>
                    ))}
                  </TextField>
                  <TextField
                    select
                    label="Users"
                    variant="filled"
                    value={userIds}
                    onChange={(event) => setUserIds(event.target.value as unknown as string[])}
                    slotProps={{
                      select: {
                        multiple: true,
                        renderValue: renderUserChips
                      }
                    }}
                  >
                    {data?.users.map((user) => (
                      <MenuItem key={user.id} value={user.id}>
                        {user.name}
                      </MenuItem>
                    ))}
                  </TextField>
                </Stack>
              )}
            </Stack>
          </>
        )}

        {selectedAction && (
          <Alert icon={false} severity="warning" sx={{ mt: 2 }}>
            <Typography variant="caption">
              ⚠️ <strong>Warning</strong>: This action will be applied to all alert rules matching
              the above filters.
            </Typography>
          </Alert>
        )}

        <Stack direction="row" spacing={2} justifyContent="flex-end" mt={3}>
          <Button onClick={handleCloseModal} variant="outlined" disabled={isLoading}>
            Cancel
          </Button>
          <Button
            onClick={handleSubmit}
            variant="contained"
            color="primary"
            disabled={!selectedAction || isLoading}
            sx={{ minWidth: 120 }}
          >
            {isLoading ? "Processing..." : "Execute Action"}
          </Button>
        </Stack>
      </Stack>
    </ModalContainer>
  );
}
