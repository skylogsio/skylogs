import { Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { IAlertRule } from "@/@types/alertRule";
import { deleteAlertRule } from "@/api/alertRule";
import AlertRuleStatus from "@/components/AlertRule/AlertRuleStatus";
import AlertRuleType from "@/components/AlertRule/AlertRuleType";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

export default function DeleteAlertRuleModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IAlertRule }) {
  const { id, name, type, status_label, count_endpoints = 0 } = data;

  const { mutate: deleteAlertRuleMutation, isPending } = useMutation({
    mutationFn: () => deleteAlertRule(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("AlertRule Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer {...props} onAfterDelete={deleteAlertRuleMutation} isLoading={isPending}>
      <Stack spacing={1}>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Name:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {name}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Type:
          </Typography>
          <AlertRuleType
            type={type}
            iconSize="1.3rem"
            typographyProps={{ variant: "subtitle2", color: "text.secondary" }}
          />
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Status:
          </Typography>
          <AlertRuleStatus status={status_label} size="small" />
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Notify:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {count_endpoints}
          </Typography>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
