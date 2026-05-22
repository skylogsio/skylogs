import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  alpha,
  Box,
  Button,
  Checkbox,
  Chip,
  FormControlLabel,
  Grid,
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
import AccessUsersAndTeams from "@/components/AccessUsersAndTeams";
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
  name: z.string().trim().min(1, "This field is Required."),
  steps: z.array(flowStepSchema).min(1, "At least one step is required"),
  isPublic: z.boolean(),
  accessTeamIds: z.array(z.string()),
  accessUserIds: z.array(z.string())
});

type FlowFormType = z.infer<typeof createFlowSchema>;
type FlowModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<IFlow>;
  onSubmit: () => void;
};

const emptyFormValues: FlowFormType = {
  name: "",
  steps: [{ type: "wait" as const, duration: 0, timeUnit: "s" as const }],
  isPublic: false,
  accessTeamIds: [],
  accessUserIds: []
};

function getFormValues(data: CreateUpdateModal<IFlow>): FlowFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return {
    name: data.name,
    steps: (data.steps.length > 0 ? data.steps : emptyFormValues.steps) as FlowFormType["steps"],
    isPublic: data.isPublic ?? false,
    accessTeamIds: data.accessTeamIds ?? [],
    accessUserIds: data.accessUserIds ?? []
  };
}

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
    defaultValues: getFormValues(data),
    mode: "onSubmit"
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
      toast.success("Endpoint Flow Created Successfully.");
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
      toast.success("Endpoint Flow Updated Successfully.");
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
        direction="row"
        onMouseDown={(event) => event.stopPropagation()}
        sx={{
          gap: 1,
          flexWrap: "wrap",
          justifyContent: "flex-start",
          float: "left"
        }}
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
    reset(getFormValues(data));
  }, [data, open, reset]);

  return (
    <ModalContainer
      title={data === "NEW" ? "Create New Flow" : "Update Flow"}
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
      maxWidth="md"
    >
      <Box component="form" onSubmit={handleSubmit(handleSubmitForm)} sx={{ width: 1, mt: 2 }}>
        <TextField
          fullWidth
          label="Name"
          variant="filled"
          error={!!errors.name}
          helperText={errors.name?.message}
          {...register("name")}
        />

        <Stack
          spacing={2}
          sx={{
            my: 2,
            p: 2,
            maxHeight: "50vh",
            overflow: "auto",
            border: 1,
            borderColor: palette.grey[200],
            borderRadius: 2
          }}
        >
          {fields.map((field, index) => (
            <Stack
              key={field.id}
              direction="row"
              spacing={2}
              sx={{
                alignItems: "center",
                justifyContent: "center"
              }}
            >
              {field.type === "wait" ? (
                <>
                  <Box
                    component="span"
                    sx={{
                      p: 1,
                      borderRadius: "50%",
                      backgroundColor: alpha(palette.warning.main, 0.1),
                      lineHeight: 0,
                      ...(index !== 0
                        ? {
                            position: "relative",
                            "&::before": {
                              content: "''",
                              position: "absolute",
                              display: "inline-block",
                              left: "50%",
                              bottom: "100%",
                              width: "1px",
                              height: "75%",
                              backgroundColor: palette.grey[300]
                            }
                          }
                        : {})
                    }}
                  >
                    <AiFillClockCircle size={26} color={palette.warning.main} />
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
                  <Box
                    sx={{
                      p: 1,
                      borderRadius: "50%",
                      backgroundColor: alpha(palette.primary.main, 0.1),

                      ...(index !== 0
                        ? {
                            position: "relative",
                            "&::before": {
                              content: "''",
                              position: "absolute",
                              display: "inline-block",
                              left: "50%",
                              bottom: "100%",
                              width: "1px",
                              height: "75%",
                              backgroundColor: palette.grey[300]
                            }
                          }
                        : {})
                    }}
                  >
                    <AiFillApi size={26} color={palette.primary.main} />
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
              <IconButton
                sx={{
                  backgroundColor: palette.grey[100],
                  transition: "all 200ms ease",
                  "&:hover": { backgroundColor: palette.grey[200] }
                }}
                onClick={() => remove(index)}
              >
                <MdDelete />
              </IconButton>
            </Stack>
          ))}
        </Stack>

        <Box sx={{ display: "flex", gap: 2, mb: 3, justifyContent: "center" }}>
          <Button
            variant="outlined"
            onClick={addWaitStep}
            startIcon={<AiFillClockCircle size={18} />}
            sx={{
              color: palette.warning.main,
              backgroundColor: alpha(palette.warning.main, 0.1),
              border: "none",
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
              backgroundColor: alpha(palette.primary.main, 0.1),
              border: "none",
              "&:hover": {
                borderColor: palette.primary.main,
                backgroundColor: "rgba(33, 150, 243, 0.04)"
              }
            }}
          >
            ADD ENDPOINTS
          </Button>
        </Box>
        <Grid size={12}>
          <AccessUsersAndTeams
            selectedTeamIds={watch("accessTeamIds")}
            selectedUserIds={watch("accessUserIds")}
            onTeamIdsChange={(teamIds) => setValue("accessTeamIds", teamIds)}
            onUserIdsChange={(userIds) => setValue("accessUserIds", userIds)}
          />
        </Grid>
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
