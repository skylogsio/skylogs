import { useEffect, useState } from "react";

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
  Typography
} from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useForm, useFieldArray, Controller } from "react-hook-form";
import { MdAccessTime, MdDelete, MdFlashOn } from "react-icons/md";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IFlow } from "@/@types/flow";
import { type CreateUpdateModal } from "@/@types/global";
import { createFlow, updateFlow, getAllEndpoints } from "@/api/flow";
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
  const [selectedEndpoints, setSelectedEndpoints] = useState<Record<number, string[]>>({});

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
      return createFlow(payload);
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
        type: "flow",
        steps: body.steps.map((step, index) => ({
          ...step,
          endpointIds: step.type === "endpoint" ? selectedEndpoints[index] || [] : undefined
        }))
      };
      return updateFlow(id, payload);
    },
    onSuccess: () => {
      toast.success("Flow Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  function handleSubmitForm(body: FlowFormType) {
    console.log(body);
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
    setSelectedEndpoints((prev) => ({ ...prev, [fields.length]: [] }));
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
      setSelectedEndpoints({});
    } else if (data) {
      reset(data as FlowFormType);
      const endpointSelections: Record<number, string[]> = {};
      data.steps.forEach((step, index) => {
        if (step.type === "endpoint" && step.endpointIds) {
          endpointSelections[index] = step.endpointIds;
        }
      });
      setSelectedEndpoints(endpointSelections);
    }
  }, [data, open, reset]);

  return (
    <ModalContainer
      title="Create New Flow"
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

        {/* Flow Steps */}
        <Box sx={{ mb: 3 }}>
          {fields.map((field, index) => (
            <Box
              key={field.id}
              sx={{
                border: "1px solid #e0e0e0",
                borderRadius: 1,
                p: 2,
                mb: 2,
                position: "relative"
              }}
            >
              <IconButton
                onClick={() => remove(index)}
                sx={{ position: "absolute", top: 8, right: 8 }}
                size="small"
              >
                <MdDelete size={16} />
              </IconButton>

              {field.type === "wait" ? (
                <Box sx={{ display: "flex", alignItems: "center", gap: 2 }}>
                  <MdAccessTime size={20} color="#ff9800" />
                  <Typography variant="body2" sx={{ minWidth: 80 }}>
                    Duration
                  </Typography>
                  <TextField
                    label="ThreadID"
                    variant="filled"
                    error={!!errors.steps?.[index]}
                    helperText={errors.steps?.[index]?.message}
                    {...register(`steps.${index}.duration`, { valueAsNumber: true })}
                  />
                  <TextField
                    label="ThreadID"
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
                </Box>
              ) : (
                <Box sx={{ display: "flex", alignItems: "center", gap: 2 }}>
                  <MdFlashOn size={20} color="#2196f3" />
                  <Typography variant="body2" sx={{ minWidth: 80 }}>
                    Endpoints
                  </Typography>
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
                  {/* <FormControl sx={{ flex: 1 }}>
                    <InputLabel>Select Endpoints</InputLabel>
                    <Select
                      multiple
                      value={selectedEndpoints[index] || []}
                      onChange={(e) => handleEndpointSelection(index, e.target.value as string[])}
                      input={<OutlinedInput label="Select Endpoints" />}
                      renderValue={(selected) => (
                        <Box sx={{ display: "flex", flexWrap: "wrap", gap: 0.5 }}>
                          {selected.map((value) => {
                            const endpoint = endpointsData?.find((ep) => ep.id === value);
                            return (
                              <Chip key={value} label={endpoint?.name || value} size="small" />
                            );
                          })}
                        </Box>
                      )}
                    >
                      {endpointsData?.map((endpoint) => (
                        <MenuItem key={endpoint.id} value={endpoint.id}>
                          {endpoint.name}
                        </MenuItem>
                      ))}
                    </Select>
                  </FormControl> */}
                </Box>
              )}
            </Box>
          ))}
        </Box>

        {/* Add Step Buttons */}
        <Box sx={{ display: "flex", gap: 2, mb: 3 }}>
          <Button
            variant="outlined"
            onClick={addWaitStep}
            startIcon={<MdAccessTime size={16} />}
            sx={{
              color: "#ff9800",
              borderColor: "#ff9800",
              "&:hover": {
                borderColor: "#ff9800",
                backgroundColor: "rgba(255, 152, 0, 0.04)"
              }
            }}
          >
            ADD WAIT
          </Button>
          <Button
            variant="outlined"
            onClick={addEndpointStep}
            startIcon={<MdFlashOn size={16} />}
            sx={{
              color: "#2196f3",
              borderColor: "#2196f3",
              "&:hover": {
                borderColor: "#2196f3",
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
          CREATE
        </Button>
      </Box>
    </ModalContainer>
  );
}
