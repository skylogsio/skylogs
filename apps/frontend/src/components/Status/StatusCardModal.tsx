import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Autocomplete, Button, Chip, Grid, TextField } from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import { CreateUpdateModal } from "@/@types/global";
import { IStatusCard } from "@/@types/status";
import { getAlertRuleTags } from "@/api/alertRule";
import { createStatusCard, udpateStatusCard } from "@/api/status";

import ModalContainer from "../Modal";
import { ModalContainerProps } from "../Modal/types";

const statusCardSchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  tags: z.array(z.string()).min(1, "This field is Required.")
});

type StatusCardType = z.infer<typeof statusCardSchema>;
type StatusCardModalProps = Pick<ModalContainerProps, "onClose" | "open"> & {
  data: CreateUpdateModal<IStatusCard>;
  onSubmit: () => void;
};

const emptyFormValues: StatusCardType = {
  name: "",
  tags: []
};

function getFormValues(data: CreateUpdateModal<IStatusCard>): StatusCardType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return {
    name: data.name,
    tags: data.tags || []
  };
}

export default function StatusCardModal({ data, open, onSubmit, onClose }: StatusCardModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    reset,
    formState: { errors }
  } = useForm<StatusCardType>({
    resolver: zodResolver(statusCardSchema),
    defaultValues: getFormValues(data),
    mode: "onSubmit"
  });

  const { data: tagsList } = useQuery({
    queryKey: ["all-alert-rule-tags"],
    queryFn: () => getAlertRuleTags()
  });

  const { mutate: createStatusCardMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: StatusCardType) => createStatusCard(body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Status Card Created Successfully.");
        onSubmit();
        onClose?.();
        reset();
      }
    }
  });

  const { mutate: updateStatusCardMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: IStatusCard["id"]; body: StatusCardType }) =>
      udpateStatusCard(id, body),
    onSuccess: (data) => {
      if (data.status) {
        toast.success("Status Card Updated Successfully.");
        onSubmit();
        onClose?.();
        reset();
      }
    }
  });

  useEffect(() => {
    reset(getFormValues(data));
  }, [data, reset]);

  function handleSubmitForm(values: StatusCardType) {
    if (data === "NEW") {
      createStatusCardMutation(values);
    } else if (data) {
      updateStatusCardMutation({ id: data.id, body: values });
    }
  }

  const handleClose = () => {
    reset(emptyFormValues);
    onClose?.();
  };

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create New" : "Update"} Status`}
      open={open}
      onClose={handleClose}
      disableEscapeKeyDown
    >
      <Grid
        component="form"
        onSubmit={handleSubmit(handleSubmitForm)}
        container
        spacing={2}
        sx={{
          width: 1,
          display: "flex",
          marginTop: 4
        }}
      >
        <Grid size={12}>
          <TextField
            label="Name"
            variant="filled"
            fullWidth
            error={!!errors.name}
            helperText={errors.name?.message}
            {...register("name")}
          />
        </Grid>
        <Grid size={12}>
          <Autocomplete
            multiple
            id="api-alert-tags"
            options={tagsList ?? []}
            freeSolo
            fullWidth
            value={watch("tags")}
            onChange={(_, value) => setValue("tags", value)}
            renderValue={(value: readonly string[], getItemProps) =>
              value.map((option: string, index: number) => {
                const { key, ...itemProps } = getItemProps({ index });
                return (
                  <Chip key={key} variant="filled" size="small" label={option} {...itemProps} />
                );
              })
            }
            renderInput={(params) => (
              <TextField
                {...params}
                slotProps={{
                  ...params.slotProps,
                  input: params.slotProps.input,
                  inputLabel: params.slotProps.inputLabel,
                  htmlInput: params.slotProps.htmlInput
                }}
                variant="filled"
                label="Tags"
                error={!!errors.tags}
                helperText={errors.tags?.message}
              />
            )}
          />
        </Grid>
        <Grid
          size={12}
          sx={{
            marginTop: 2
          }}
        >
          <Button
            disabled={isCreating || isUpdating}
            type="submit"
            variant="contained"
            size="large"
            fullWidth
          >
            {data === "NEW" ? "Create" : "Update"}
          </Button>
        </Grid>
      </Grid>
    </ModalContainer>
  );
}
