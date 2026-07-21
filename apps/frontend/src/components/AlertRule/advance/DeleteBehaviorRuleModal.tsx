import { useParams } from "next/navigation";

import { Box, Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import { deleteBehaviorRule } from "@/api/alertRule";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

import BehaviorRuleChip from "./BehaviorRuleChip";
import BehaviorRuleDetails from "./BehaviorRuleDetails";
import type { BehaviorRuleItem } from "./BehaviorRuleType";

export interface DeleteBehaviorRuleModalProps extends DeleteModalProps {
  data: BehaviorRuleItem;
}

export default function DeleteBehaviorRuleModal({
  data,
  onAfterDelete,
  ...props
}: DeleteBehaviorRuleModalProps) {
  const { alertId } = useParams<{ alertId: string }>();
  const { id, name, type } = data;

  const { mutate: deleteBehaviorRuleMutation, isPending } = useMutation({
    mutationFn: () => deleteBehaviorRule(alertId, id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("Behavior Rule Deleted Successfully.");
    },
    onError() {
      toast.error("Failed to delete Behavior Rule.");
    }
  });

  return (
    <DeleteModalContainer
      {...props}
      onAfterDelete={deleteBehaviorRuleMutation}
      isLoading={isPending}
    >
      <Stack
        spacing={1.5}
        sx={{
          width: 1,
          maxHeight: 240,
          overflowY: "auto",
          pr: 0.25
        }}
      >
        <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
          <Typography variant="subtitle2" sx={{ color: "text.secondary", fontWeight: 700 }}>
            Name:
          </Typography>
          <Typography variant="subtitle2" sx={{ color: "text.primary", fontWeight: 600 }}>
            {name}
          </Typography>
        </Stack>

        <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
          <Typography variant="subtitle2" sx={{ color: "text.secondary", fontWeight: 700 }}>
            Type:
          </Typography>
          <BehaviorRuleChip label={type} showNotification showTemplate size="small" />
        </Stack>

        <Box
          sx={{
            width: 1,
            pl: 1.25
          }}
        >
          <BehaviorRuleDetails item={data} />
        </Box>
      </Stack>
    </DeleteModalContainer>
  );
}
