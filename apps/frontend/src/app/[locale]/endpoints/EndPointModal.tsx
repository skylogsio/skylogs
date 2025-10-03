import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Button,
  Checkbox,
  FormControlLabel,
  Grid2 as Grid,
  MenuItem,
  TextField
} from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IEndpoint } from "@/@types/endpoint";
import { type CreateUpdateModal } from "@/@types/global";
import { createEndpoint, updateEndpoint } from "@/api/endpoint";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

const ENDPOINTS_TYPE = ["sms", "telegram", "teams", "call", "email", "matter-most"] as const;

const createEndpointSchema = z.object({
  name: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  type: z.enum(ENDPOINTS_TYPE, {
    required_error: "This field is Required.",
    message: "This field is Required."
  }),
  value: z
    .string({ required_error: "This field is Required." })
    .refine((data) => data.trim() !== "", {
      message: "This field is Required."
    }),
  isPublic: z.optional(z.boolean()).default(false),
  threadId: z.optional(z.string()).nullable(),
  botToken: z.optional(z.string()).nullable()
});

type EndpointFormType = z.infer<typeof createEndpointSchema> & { chatId?: string };
type EndpointModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<IEndpoint>;
  onSubmit: () => void;
};

const defaultValues = {
  name: "",
  type: undefined,
  value: ""
};

export default function EndPointModal({ open, onClose, data, onSubmit }: EndpointModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    formState: { errors }
  } = useForm<EndpointFormType>({
    resolver: zodResolver(createEndpointSchema),
    defaultValues
  });

  const { mutate: createEndpointMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: EndpointFormType) => createEndpoint(body),
    onSuccess: () => {
      toast.success("EndPoint Created Successfully.");
      onSubmit();
      onClose?.();
    }
  });
  const { mutate: updateEndpointMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: EndpointFormType }) => updateEndpoint(id, body),
    onSuccess: () => {
      toast.success("EndPoint Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  function handleSubmitForm(body: EndpointFormType) {
    if (data === "NEW") {
      createEndpointMutation(body);
    } else if (data) {
      updateEndpointMutation({ id: data.id, body });
    }
  }

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else {
      if (data?.type === "telegram") {
        reset({ ...data, value: data.chatId });
      } else {
        reset(data as EndpointFormType);
      }
    }
  }, [data, open, reset]);

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create New" : "Update"} Endpoint`}
      open={open}
      onClose={onClose}
      disableEscapeKeyDown
    >
      <Grid
        component="form"
        onSubmit={handleSubmit(handleSubmitForm, (error) => console.log(error))}
        container
        spacing={2}
        width="100%"
        display="flex"
        marginTop="2rem"
      >
        <Grid size={6}>
          <TextField
            label="Name"
            variant="filled"
            error={!!errors.name}
            helperText={errors.name?.message}
            {...register("name")}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            label="Type"
            variant="filled"
            error={!!errors.type}
            helperText={errors.type?.message}
            {...register("type")}
            value={watch("type") ?? ""}
            select
          >
            {ENDPOINTS_TYPE.map((item) => (
              <MenuItem
                key={item}
                value={item}
                sx={{ textTransform: item === "sms" ? "uppercase" : "capitalize" }}
              >
                {item.replace("-", " ")}
              </MenuItem>
            ))}
          </TextField>
        </Grid>
        <Grid size={watch("type") === "telegram" ? 6 : 12}>
          <TextField
            label={watch("type") === "telegram" ? "ChatID" : "Value"}
            variant="filled"
            error={!!errors.value}
            helperText={errors.value?.message}
            {...register("value")}
          />
        </Grid>
        {watch("type") === "telegram" && (
          <Grid size={6}>
            <TextField
              label="ThreadID"
              variant="filled"
              error={!!errors.threadId}
              helperText={errors.threadId?.message}
              {...register("threadId")}
            />
          </Grid>
        )}
        {watch("type") === "telegram" && (
          <Grid size={12}>
            <TextField
              label="Bot Token"
              variant="filled"
              error={!!errors.botToken}
              helperText={errors.botToken?.message}
              {...register("botToken")}
            />
          </Grid>
        )}
        <Grid size={12}>
          <FormControlLabel
            sx={{ margin: 0 }}
            label="Is Public"
            control={
              <Checkbox
                checked={watch("isPublic")}
                onChange={(_, checked) => setValue("isPublic", checked)}
              />
            }
          />
        </Grid>
        <Grid size={12} marginTop="1rem">
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
