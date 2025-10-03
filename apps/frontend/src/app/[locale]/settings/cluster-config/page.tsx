"use client";
import { useEffect, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Button,
  Card,
  CardContent,
  FormControl,
  FormControlLabel,
  Grid2 as Grid,
  Radio,
  RadioGroup,
  Stack,
  TextField,
  Typography,
  useTheme,
  Alert,
  CircularProgress,
  alpha
} from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Controller, useForm } from "react-hook-form";
import { HiServer } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import type { IClusterConfigRequest } from "@/@types/settings/clusterConfig";
import { createUpdateClusterConfig, getClusterConfig } from "@/api/setttings/clusterConfig";

const clusterSchema = z
  .object({
    type: z.enum(["main", "agent"], {
      required_error: "Cluster type is required.",
      invalid_type_error: "Please select a valid cluster type."
    }),
    sourceUrl: z.string().url({ message: "Source Url is not valid." }).optional(),
    sourceToken: z.string().optional()
  })
  .refine(
    (data) => {
      if (data.type === "agent") {
        return data.sourceUrl && data.sourceUrl.trim() !== "";
      }
      return true;
    },
    {
      message: "Source URL are required for agent type.",
      path: ["sourceUrl"]
    }
  )
  .refine(
    (data) => {
      if (data.type === "agent") {
        return data.sourceToken && data.sourceToken.trim() !== "";
      }
      return true;
    },
    {
      message: "Source Token are required for agent type.",
      path: ["sourceToken"]
    }
  );

type ClusterFormType = z.infer<typeof clusterSchema>;

const defaultValues: ClusterFormType = {
  type: "main",
  sourceUrl: "",
  sourceToken: ""
};

export default function ClusterConfigurationPage() {
  const { palette } = useTheme();
  const [isInitialized, setIsInitialized] = useState(false);

  const {
    register,
    handleSubmit,
    reset,
    control,
    watch,
    formState: { errors }
  } = useForm<ClusterFormType>({
    resolver: zodResolver(clusterSchema),
    defaultValues
  });

  const watchedType = watch("type");

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["cluster-config"],
    queryFn: () => getClusterConfig(),
    retry: false,
    refetchOnWindowFocus: false
  });

  const { mutate: createUpdateMutation, isPending } = useMutation({
    mutationFn: (body: IClusterConfigRequest) => createUpdateClusterConfig(body),
    onSuccess: () => {
      toast.success("Cluster Configuration Saved Successfully.");
      refetch();
    }
  });

  function handleSubmitForm(formData: ClusterFormType) {
    const payload: IClusterConfigRequest = {
      type: formData.type
    };
    if (formData.type === "agent") {
      payload.sourceUrl = formData.sourceUrl;
      payload.sourceToken = formData.sourceToken;
    }
    createUpdateMutation(payload);
  }

  useEffect(() => {
    if (data && !isInitialized) {
      reset({
        type: data.type,
        sourceUrl: data.sourceUrl || "",
        sourceToken: data.sourceToken || ""
      });
      setIsInitialized(true);
    }
  }, [data, reset, isInitialized]);

  if (isLoading) {
    return (
      <Stack spacing={2} alignItems="center" justifyContent="center" minHeight="400px">
        <CircularProgress />
        <Typography variant="h6">Loading cluster configuration...</Typography>
      </Stack>
    );
  }

  if (error && !data) {
    return (
      <Stack spacing={3}>
        <Stack direction="row" alignItems="center" spacing={2}>
          <HiServer size="2rem" color={palette.primary.main} />
          <Typography variant="h5" fontSize="1.8rem" fontWeight="700">
            Cluster Configuration
          </Typography>
        </Stack>
        <Alert severity="info">
          No cluster configuration found. You can create a new configuration below.
        </Alert>
        <Card sx={{ backgroundColor: palette.background.paper }}>
          <CardContent sx={{ padding: 4 }}>
            <Grid component="form" onSubmit={handleSubmit(handleSubmitForm)} container spacing={3}>
              <Grid size={12}>
                <FormControl component="fieldset" error={!!errors.type}>
                  <Typography variant="h6" marginBottom={3}>
                    Cluster Type
                  </Typography>
                  <Controller
                    name="type"
                    control={control}
                    render={({ field }) => (
                      <RadioGroup {...field} row sx={{ gap: 3 }}>
                        <FormControlLabel
                          value="main"
                          control={<Radio />}
                          label={
                            <Stack>
                              <Typography variant="body1" fontWeight="500">
                                Main Cluster
                              </Typography>
                              <Typography variant="caption" color="textSecondary">
                                Primary cluster node that manages the system
                              </Typography>
                            </Stack>
                          }
                        />
                        <FormControlLabel
                          value="agent"
                          control={<Radio />}
                          label={
                            <Stack>
                              <Typography variant="body1" fontWeight="500">
                                Agent Cluster
                              </Typography>
                              <Typography variant="caption" color="textSecondary">
                                Secondary node that connects to a main cluster
                              </Typography>
                            </Stack>
                          }
                        />
                      </RadioGroup>
                    )}
                  />
                  {errors.type && (
                    <Typography color="error" variant="caption">
                      {errors.type.message}
                    </Typography>
                  )}
                </FormControl>
              </Grid>

              {watchedType === "agent" && (
                <>
                  <Grid size={12}>
                    <TextField
                      label="Source URL"
                      placeholder="http://127.0.0.1:8080"
                      variant="filled"
                      fullWidth
                      error={!!errors.sourceUrl}
                      helperText={errors.sourceUrl?.message}
                      {...register("sourceUrl")}
                    />
                  </Grid>
                  <Grid size={12}>
                    <TextField
                      label="Source Token"
                      variant="filled"
                      fullWidth
                      type="password"
                      error={!!errors.sourceToken}
                      helperText={errors.sourceToken?.message}
                      {...register("sourceToken")}
                    />
                  </Grid>
                </>
              )}

              <Grid size={12}>
                <Button
                  type="submit"
                  variant="contained"
                  size="large"
                  disabled={isPending}
                  startIcon={isPending ? <CircularProgress size={20} /> : <HiServer />}
                  sx={{ marginTop: 2 }}
                >
                  {isPending ? "Saving..." : "Save Configuration"}
                </Button>
              </Grid>
            </Grid>
          </CardContent>
        </Card>
      </Stack>
    );
  }

  return (
    <Stack spacing={3}>
      <Stack direction="row" alignItems="center" spacing={2}>
        <HiServer size="2rem" color={palette.primary.main} />
        <Typography variant="h5" fontSize="1.8rem" fontWeight="700">
          Cluster Configuration
        </Typography>
      </Stack>

      {data && (
        <Alert severity="success">
          Current configuration: <strong>{data.type}</strong> cluster
          {data.type === "agent" && data.sourceUrl && (
            <>
              connected to <strong>{data.sourceUrl}</strong>
            </>
          )}
        </Alert>
      )}

      <Card sx={{ backgroundColor: palette.background.paper, boxShadow: "none" }}>
        <CardContent sx={{ padding: 4 }}>
          <Grid component="form" onSubmit={handleSubmit(handleSubmitForm)} container spacing={3}>
            <Grid size={12}>
              <FormControl component="fieldset" error={!!errors.type}>
                <Typography variant="h6" gutterBottom>
                  Cluster Type
                </Typography>
                <Controller
                  name="type"
                  control={control}
                  render={({ field }) => (
                    <RadioGroup {...field} row sx={{ gap: 3 }}>
                      <FormControlLabel
                        value="main"
                        control={<Radio />}
                        label={
                          <Stack>
                            <Typography variant="body1" fontWeight="500">
                              Main Cluster
                            </Typography>
                            <Typography variant="caption" color="textSecondary">
                              Primary cluster node that manages the system
                            </Typography>
                          </Stack>
                        }
                      />
                      <FormControlLabel
                        value="agent"
                        control={<Radio />}
                        label={
                          <Stack>
                            <Typography variant="body1" fontWeight="500">
                              Agent Cluster
                            </Typography>
                            <Typography variant="caption" color="textSecondary">
                              Secondary node that connects to a main cluster
                            </Typography>
                          </Stack>
                        }
                      />
                    </RadioGroup>
                  )}
                />
                {errors.type && (
                  <Typography color="error" variant="caption">
                    {errors.type.message}
                  </Typography>
                )}
              </FormControl>
            </Grid>

            {watchedType === "agent" && (
              <>
                <Grid size={12}>
                  <TextField
                    label="Source URL"
                    variant="filled"
                    fullWidth
                    error={!!errors.sourceUrl}
                    helperText={errors.sourceUrl?.message}
                    {...register("sourceUrl")}
                  />
                </Grid>
                <Grid size={12}>
                  <TextField
                    label="Source Token"
                    variant="filled"
                    fullWidth
                    type="password"
                    error={!!errors.sourceToken}
                    helperText={errors.sourceToken?.message}
                    {...register("sourceToken")}
                  />
                </Grid>
              </>
            )}

            <Grid size={12}>
              <Button
                type="submit"
                variant="contained"
                size="large"
                disabled={isPending}
                startIcon={isPending ? <CircularProgress size={20} /> : <HiServer />}
                sx={{ marginTop: 2 }}
              >
                {isPending ? "Saving..." : "Save Configuration"}
              </Button>
            </Grid>
          </Grid>
        </CardContent>
      </Card>

      <Card sx={{ backgroundColor: alpha(palette.info.light, 0.08), boxShadow: "none" }}>
        <CardContent>
          <Typography variant="h6" color="info.main" marginBottom={1}>
            Configuration Information
          </Typography>
          <Stack spacing={1}>
            <Typography variant="body2" color="textSecondary">
              <strong>Main Cluster:</strong> Acts as the primary node that manages the entire
              system. No additional configuration is required.
            </Typography>
            <Typography variant="body2" color="textSecondary">
              <strong>Agent Cluster:</strong> Connects to an existing main cluster. Requires both
              Source URL and Source Token for authentication and communication.
            </Typography>
          </Stack>
        </CardContent>
      </Card>
    </Stack>
  );
}
