import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Box,
  Button,
  Checkbox,
  Chip,
  FormControlLabel,
  IconButton,
  MenuItem,
  Stack,
  TextField,
  useTheme
} from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useForm, useFieldArray, Controller } from "react-hook-form";
import { AiFillClockCircle, AiFillApi } from "react-icons/ai";
import { MdDelete } from "react-icons/md";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IFlow } from "@/@types/flow";
import { type CreateUpdateModal } from "@/@types/global";
import { createEndpoint, updateEndpoint } from "@/api/endpoint";
import { getAllEndpoints } from "@/api/flow";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

const TIME_UNITS = [
  { value: "s", label: "Seconds" },
  { value: "m", label: "Minutes" },
  { value: "h", label: "Hours" }
] as const;

const flowStepSchema = z.discriminatedUnion("type", [
  z.object({
    type: z.literal("wait"),
    duration: z.number().min(1, "Duration must be at least 1"),
    timeUnit: z.enum(["s", "m", "h"])
  }),
  z.object({
    type: z.literal("endpoint"),
    endpointIds: z.array(z.string()).min(1, "At least one endpoint is required")
  })
]);

const createFlowSchema = z.object({
  name: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  steps: z.array(flowStepSchema).min(1, "At least one step is required"),
  isPublic: z.boolean().default(false)
});

type FlowFormType = z.infer<typeof createFlowSchema>;
type FlowModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<IFlow>;
  onSubmit: () => void;
};

const defaultValues: FlowFormType = {
  name: "",
  steps: [{ type: "wait" as const, duration: 0, timeUnit: "s" as const }],
  isPublic: false
};

export default function FlowModal({ open, onClose, data, onSubmit }: FlowModalProps) {
  const { palette } = useTheme();
  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    getValues,
    control,
    formState: { errors }
  } = useForm<FlowFormType>({
    resolver: zodResolver(createFlowSchema),
    defaultValues
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "steps"
  });

  const { data: endpointsData } = useQuery({
    queryKey: ["endpoints"],
    queryFn: () => getAllEndpoints()
  });

  const { mutate: createFlowMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: FlowFormType) => {
      const payload = {
        ...body,
        type: "flow"
      };
      return createEndpoint(payload);
    },
    onSuccess: () => {
      toast.success("Flow Created Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  const { mutate: updateFlowMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: FlowFormType }) => {
      const payload = {
        ...body,
        type: "flow"
      };
      return updateEndpoint(id, payload);
    },
    onSuccess: () => {
      toast.success("Flow Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  function handleSubmitForm(body: FlowFormType) {
    if (data === "NEW") {
      createFlowMutation(body);
    } else if (data) {
      updateFlowMutation({ id: data.id, body });
    }
  }

  function addWaitStep() {
    append({ type: "wait" as const, duration: 0, timeUnit: "s" as const });
  }

  function addEndpointStep() {
    append({ type: "endpoint" as const, endpointIds: [] });
  }

  const handleRemoveEndpointChip = (endpointId: string, index: number) => {
    const selected = getValues(`steps.${index}.endpointIds`) as string[];
    setValue(
      `steps.${index}.endpointIds`,
      selected.filter((id) => id !== endpointId)
    );
  };

  const renderEndpointChips = (selectedIds: unknown, index: number) => {
    const selected =
      endpointsData?.filter((endpoint) => (selectedIds as string[]).includes(endpoint.id)) ?? [];
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
            onDelete={() => handleRemoveEndpointChip(endpoint.id, index)}
          />
        ))}
      </Stack>
    );
  };

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else if (data) {
      reset(data as FlowFormType);
      const endpointSelections: Record<number, string[]> = {};
      data.steps.forEach((step, index) => {
        if (step.type === "endpoint" && step.endpointIds) {
          endpointSelections[index] = step.endpointIds;
        }
      });
    }
  }, [data, open, reset]);

  return (
    <ModalContainer
      title={data === "NEW" ? "Create New Flow" : "Update Flow"}
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
      maxWidth="md"
    >
      <Box
        component="form"
        onSubmit={handleSubmit(handleSubmitForm, (error) => console.log(error))}
        sx={{ width: "100%", mt: 2 }}
      >
        <TextField
          fullWidth
          label="Name"
          variant="filled"
          error={!!errors.name}
          helperText={errors.name?.message}
          {...register("name")}
        />

        <Stack spacing={2} my={2}>
          {fields.map((field, index) => (
            <Stack key={field.id} direction="row" alignItems="center" spacing={2}>
              {field.type === "wait" ? (
                <>
                  <Box component="span">
                    <AiFillClockCircle size={28} color={palette.warning.main} />
                  </Box>
                  <TextField
                    label="Duration"
                    variant="filled"
                    error={!!errors.steps?.[index]}
                    helperText={errors.steps?.[index]?.message}
                    {...register(`steps.${index}.duration`, { valueAsNumber: true })}
                  />
                  <TextField
                    label="Time Unit"
                    variant="filled"
                    error={!!errors.steps?.[index]}
                    helperText={errors.steps?.[index]?.message}
                    {...register(`steps.${index}.timeUnit`)}
                    value={watch(`steps.${index}.timeUnit`) ?? "s"}
                    select
                  >
                    {TIME_UNITS.map((unit) => (
                      <MenuItem key={unit.value} value={unit.value}>
                        {unit.label}
                      </MenuItem>
                    ))}
                  </TextField>
                </>
              ) : (
                <>
                  <Box component="span">
                    <AiFillApi size={28} color={palette.primary.main} />
                  </Box>
                  <Controller
                    control={control}
                    name={`steps.${index}.endpointIds`}
                    render={({ field }) => (
                      <TextField
                        {...field}
                        select
                        label="Endpoints"
                        variant="filled"
                        error={!!errors.steps?.[index]}
                        helperText={errors.steps?.[index]?.message}
                        value={field.value ?? []}
                        slotProps={{
                          select: {
                            multiple: true,
                            renderValue: (selectedEndpoints) =>
                              renderEndpointChips(selectedEndpoints, index)
                          }
                        }}
                      >
                        {endpointsData?.map((endpoint) => (
                          <MenuItem key={endpoint.id} value={endpoint.id}>
                            {endpoint.name}
                          </MenuItem>
                        ))}
                      </TextField>
                    )}
                  />
                </>
              )}
              <IconButton onClick={() => remove(index)}>
                <MdDelete />
              </IconButton>
            </Stack>
          ))}
        </Stack>

        <Box sx={{ display: "flex", gap: 2, mb: 3 }}>
          <Button
            variant="outlined"
            onClick={addWaitStep}
            startIcon={<AiFillClockCircle size={18} />}
            sx={{
              color: palette.warning.main,
              borderColor: palette.warning.main,
              "&:hover": {
                borderColor: palette.warning.main,
                backgroundColor: "rgba(255, 152, 0, 0.04)"
              }
            }}
          >
            ADD WAIT
          </Button>
          <Button
            variant="outlined"
            onClick={addEndpointStep}
            startIcon={<AiFillApi size={18} color={palette.primary.main} />}
            sx={{
              color: palette.primary.main,
              borderColor: palette.primary.main,
              "&:hover": {
                borderColor: palette.primary.main,
                backgroundColor: "rgba(33, 150, 243, 0.04)"
              }
            }}
          >
            ADD ENDPOINTS
          </Button>
        </Box>

        <FormControlLabel
          sx={{ mb: 3 }}
          label="Is Public"
          control={
            <Checkbox
              checked={watch("isPublic")}
              onChange={(_, checked) => setValue("isPublic", checked)}
            />
          }
        />

        <Button
          disabled={isCreating || isUpdating}
          type="submit"
          variant="contained"
          size="large"
          fullWidth
        >
          {data === "NEW" ? "Create" : "Update"}
        </Button>
      </Box>
    </ModalContainer>
  );
}
