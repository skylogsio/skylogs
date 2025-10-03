import { Box, Chip, Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { MdAccessTime, MdFlashOn } from "react-icons/md";
import { toast } from "react-toastify";

import type { IFlow } from "@/@types/flow";
import { deleteFlow } from "@/api/flow";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

export default function DeleteFlowModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IFlow }) {
  const { id, name, steps } = data;

  const { mutate: deleteFlowMutation, isPending } = useMutation({
    mutationFn: () => deleteFlow(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("Flow Deleted Successfully.");
    }
  });

  const renderStepIcon = (type: string) => {
    if (type === "wait") {
      return <MdAccessTime size={16} color="#ff9800" />;
    }
    return <MdFlashOn size={16} color="#2196f3" />;
  };

  return (
    <DeleteModalContainer {...props} onAfterDelete={deleteFlowMutation} isLoading={isPending}>
      <Stack spacing={2}>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Name:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {name}
          </Typography>
        </Stack>

        <Stack spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Steps:
          </Typography>
          <Box sx={{ display: "flex", flexWrap: "wrap", gap: 1 }}>
            {steps.map((step, index) => (
              <Chip
                key={index}
                icon={renderStepIcon(step.type)}
                label={step.type === "wait" ? `${step.duration}${step.timeUnit}` : "Endpoint"}
                size="small"
                variant="outlined"
                sx={{
                  color: step.type === "wait" ? "#ff9800" : "#2196f3",
                  borderColor: step.type === "wait" ? "#ff9800" : "#2196f3"
                }}
              />
            ))}
          </Box>
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
