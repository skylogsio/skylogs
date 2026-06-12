import { useParams } from "next/navigation";

import { Chip, Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import { deleteBehaviorRule } from "@/api/alertRule";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

import BehaviorRuleChip from "./BehaviorRuleChip";
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
      <Stack spacing={1.5}>
        <Stack direction="row" spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: "bold"
            }}
          >
            Name:
          </Typography>
          <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
            {name}
          </Typography>
        </Stack>

        <Stack direction="row" spacing={1}>
          <Typography
            variant="subtitle2"
            sx={{
              color: "text.secondary",
              fontWeight: "bold"
            }}
          >
            Type:
          </Typography>
          <BehaviorRuleChip label={type} sx={{ height: 26 }} />
        </Stack>

        {(type === "notification" || type === "template") && (
          <>
            <Stack direction="row" spacing={1} sx={{ alignItems: "flex-start" }}>
              <Typography
                variant="subtitle2"
                sx={{
                  color: "text.secondary",
                  fontWeight: "bold"
                }}
              >
                Endpoints:
              </Typography>
              <Stack direction="row" spacing={1} sx={{ flexWrap: "wrap" }}>
                {"endpoints" in data && data.endpoints.length > 0 ? (
                  data.endpoints.map((ep) => <Chip key={ep.id} label={ep.name} size="small" />)
                ) : (
                  <Typography variant="caption" sx={{ color: "text.secondary" }}>
                    None
                  </Typography>
                )}
              </Stack>
            </Stack>

            {type === "notification" && "filters" in data && (
              <Stack direction="row" spacing={1}>
                <Typography
                  variant="subtitle2"
                  sx={{
                    color: "text.secondary",
                    fontWeight: "bold"
                  }}
                >
                  Filters Count:
                </Typography>
                <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
                  {data.filters?.length ?? 0}
                </Typography>
              </Stack>
            )}
          </>
        )}

        {type === "silent" && (
          <>
            <Stack direction="row" spacing={1} sx={{ alignItems: "flex-start" }}>
              <Typography
                variant="subtitle2"
                sx={{
                  color: "text.secondary",
                  fontWeight: "bold"
                }}
              >
                Depends On Rules:
              </Typography>
              <Stack direction="row" spacing={1} sx={{ flexWrap: "wrap" }}>
                {data.dependsOnAlertRules.length > 0 ? (
                  data.dependsOnAlertRules.map((rule) => (
                    <Chip key={rule.id} label={rule.name} size="small" />
                  ))
                ) : (
                  <Typography variant="caption" sx={{ color: "text.secondary" }}>
                    None
                  </Typography>
                )}
              </Stack>
            </Stack>

            <Stack direction="row" spacing={1}>
              <Typography
                variant="subtitle2"
                sx={{
                  color: "text.secondary",
                  fontWeight: "bold"
                }}
              >
                Trigger State:
              </Typography>
              <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
                {data.triggerState}
              </Typography>
            </Stack>
          </>
        )}
      </Stack>
    </DeleteModalContainer>
  );
}
