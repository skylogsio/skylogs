import { useEffect, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Button,
  Checkbox,
  Collapse,
  collapseClasses,
  FormControlLabel,
  Grid,
  MenuItem,
  Stack,
  TextField
} from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IEndpoint } from "@/@types/endpoint";
import { type CreateUpdateModal } from "@/@types/global";
import { createEndpoint, sendOTP, updateEndpoint } from "@/api/endpoint";
import AccessUsersAndTeams from "@/components/AccessUsersAndTeams";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import OTP from "@/components/OTP";

const ENDPOINTS_TYPE = [
  "sms",
  "call",
  "email",
  "telegram",
  "bale",
  "teams",
  "matter-most",
  "discord"
] as const;

const OTP_REQUIRED_ENDPOINT_TYPES = ENDPOINTS_TYPE.slice(0, 3);

const endpointSchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  type: z.enum(ENDPOINTS_TYPE, "This field is Required."),
  value: z.string().trim().min(1, "This field is Required."),
  otpCode: z.string().optional(),
  isPublic: z.boolean(),
  threadId: z.string().nullable().optional(),
  botToken: z.string().nullable().optional(),
  accessTeamIds: z.array(z.string()),
  accessUserIds: z.array(z.string())
});

type EndpointFormType = z.infer<typeof endpointSchema> & { chatId?: string };
type EndpointModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<IEndpoint>;
  onSubmit: () => void;
};

const emptyFormValues: EndpointFormType = {
  name: "",
  type: ENDPOINTS_TYPE[0],
  value: "",
  isPublic: false,
  accessTeamIds: [],
  accessUserIds: []
};

function getFormValues(data: CreateUpdateModal<IEndpoint>): EndpointFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  if (data.type === "telegram" || data.type === "bale") {
    return {
      name: data.name,
      type: data.type,
      value: data.chatId ?? data.value,
      isPublic: data.isPublic ?? false,
      threadId: data.type === "telegram" ? (data.threadId ?? null) : null,
      botToken: null,
      accessTeamIds: data.accessTeamIds ?? [],
      accessUserIds: data.accessUserIds ?? []
    };
  }

  return {
    name: data.name,
    type: data.type,
    value: data.value,
    isPublic: data.isPublic ?? false,
    accessTeamIds: data.accessTeamIds ?? [],
    accessUserIds: data.accessUserIds ?? []
  };
}

export default function EndPointModal({ open, onClose, data, onSubmit }: EndpointModalProps) {
  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    getValues,
    setError,
    formState: { errors }
  } = useForm<EndpointFormType>({
    resolver: zodResolver(endpointSchema),
    defaultValues: getFormValues(data),
    mode: "onSubmit"
  });
  const [isOTPSent, setIsOTPSent] = useState(false);
  const [remainedSeconds, setRemainedSeconds] = useState(0);

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
  const { mutate: sendOTPMutation, isPending: isSendingOTP } = useMutation({
    mutationFn: (body: unknown) => sendOTP(body),
    onSuccess: (data) => {
      toast.success(data.message);
      setIsOTPSent(true);
      setRemainedSeconds(data.timeLeft);
    }
  });

  function handleSubmitForm(body: EndpointFormType) {
    const trimmedOTP = body.otpCode?.trim() ?? "";
    if (OTP_REQUIRED_ENDPOINT_TYPES.includes(body.type) && trimmedOTP.length !== 5) {
      setError("otpCode", { message: "Enter a valid OTP code." });
      return null;
    }
    if (data === "NEW") {
      createEndpointMutation(body);
    } else if (data) {
      updateEndpointMutation({ id: data.id, body });
    }
  }

  function handleSendOTP() {
    const [type, value] = getValues(["type", "value"]);
    if (value.trim().length === 0) {
      setError("value", { message: "This field is Required." });
      return;
    }
    const body = { type, value };
    sendOTPMutation(body);
  }

  const showOTPSection =
    OTP_REQUIRED_ENDPOINT_TYPES.includes(getValues("type")) &&
    (data === "NEW" || data?.value !== watch("value") || data?.type !== getValues("type"));

  useEffect(() => {
    reset(getFormValues(data));
  }, [data, open, reset]);

  useEffect(() => {
    if (remainedSeconds > 0) {
      const timer = setTimeout(() => setRemainedSeconds((prev) => prev - 1), 1000);
      return () => clearTimeout(timer);
    }
  }, [remainedSeconds]);

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create New" : "Update"} Endpoint`}
      open={open}
      onClose={onClose}
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
            label={watch("type") === "telegram" || watch("type") === "bale" ? "ChatID" : "Value"}
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
        {(watch("type") === "telegram" || watch("type") === "bale") && (
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
          <AccessUsersAndTeams
            selectedTeamIds={watch("accessTeamIds")}
            selectedUserIds={watch("accessUserIds")}
            onTeamIdsChange={(teamIds) => setValue("accessTeamIds", teamIds)}
            onUserIdsChange={(userIds) => setValue("accessUserIds", userIds)}
          />
        </Grid>
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
        {showOTPSection && (
          <Grid size={12}>
            <Stack
              direction="row"
              spacing={2}
              sx={{
                alignItems: "center"
              }}
            >
              <Collapse
                in={isOTPSent}
                orientation="horizontal"
                unmountOnExit
                mountOnEnter
                sx={{
                  flex: 1,
                  [`& .${collapseClasses.wrapperInner}`]: {
                    flex: 1
                  }
                }}
              >
                <OTP
                  variant="filled"
                  error={!!errors.otpCode}
                  helperText={errors.otpCode?.message}
                  {...register("otpCode")}
                  placeholder="-----"
                  sx={{ width: 1 }}
                />
              </Collapse>
              <Button
                variant="contained"
                size="large"
                fullWidth
                type="button"
                onClick={handleSendOTP}
                disabled={isSendingOTP || (isOTPSent && !!remainedSeconds)}
                sx={{ maxWidth: isOTPSent ? 130 : 1, transition: "all 0.3s ease" }}
              >
                {isOTPSent
                  ? remainedSeconds > 0
                    ? `${`0${parseInt(String(remainedSeconds / 60))}`.slice(-2)}:${`0${parseInt(String(remainedSeconds % 60))}`.slice(
                        -2
                      )}`
                    : "Resend"
                  : "Send OTP Code"}
              </Button>
            </Stack>
          </Grid>
        )}
        {(!showOTPSection || (showOTPSection && isOTPSent)) && (
          <Grid size={12}>
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
        )}
      </Grid>
    </ModalContainer>
  );
}
