import { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Grid, TextField } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "react-toastify";
import { z } from "zod";

import type { ICluster } from "@/@types/cluster";
import { type CreateUpdateModal } from "@/@types/global";
import { createCluster, updateCluster } from "@/api/cluster";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";

const clusterSchema = z.object({
  name: z.string().trim().min(1, "This field is Required."),
  type: z.string().trim().min(1, "This field is Required."),
  url: z
    .string()
    .trim()
    .min(1, "This field is Required.")
    .pipe(z.url( "Please enter a valid URL." ))
});

type ClusterFormType = z.infer<typeof clusterSchema>;
type ClusterModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: CreateUpdateModal<ICluster>;
  onSubmit: () => void;
};

const emptyFormValues: ClusterFormType = {
  name: "",
  type: "agent",
  url: ""
};

function getFormValues(data: CreateUpdateModal<ICluster>): ClusterFormType {
  if (!data || data === "NEW") {
    return emptyFormValues;
  }

  return {
    name: data.name,
    type: data.type,
    url: data.url
  };
}

// const clusterTypes = [
//   { value: "agent", label: "Agent" },
//   { value: "server", label: "Server" },
//   { value: "worker", label: "Worker" },
//   { value: "master", label: "Master" }
// ];

export default function ClusterModal({ open, onClose, data, onSubmit }: ClusterModalProps) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors }
  } = useForm<ClusterFormType>({
    resolver: zodResolver(clusterSchema),
    defaultValues: getFormValues(data),
    mode: "onSubmit"
  });

  const { mutate: createClusterMutation, isPending: isCreating } = useMutation({
    mutationFn: (body: ClusterFormType) => createCluster(body),
    onSuccess: () => {
      toast.success("Cluster Created Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  const { mutate: updateClusterMutation, isPending: isUpdating } = useMutation({
    mutationFn: ({ id, body }: { id: string; body: ClusterFormType }) => updateCluster(id, body),
    onSuccess: () => {
      toast.success("Cluster Updated Successfully.");
      onSubmit();
      onClose?.();
    }
  });

  // function renderClusterTypeList() {
  //   return clusterTypes.map((type) => (
  //     <MenuItem key={type.value} value={type.value}>
  //       <Typography component="span">{type.label}</Typography>
  //     </MenuItem>
  //   ));
  // }

  function handleSubmitForm(body: ClusterFormType) {
    if (data === "NEW") {
      createClusterMutation(body);
    } else if (data) {
      updateClusterMutation({ id: data.id, body });
    }
  }

  useEffect(() => {
    reset(getFormValues(data));
  }, [data, reset]);

  return (
    <ModalContainer
      title={`${data === "NEW" ? "Create New" : "Update"} Cluster`}
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
          width: "100%",
          display: "flex",
          marginTop: 4
        }}
      >
        {/*<Grid size={12}>*/}
        {/*  <TextField*/}
        {/*    label="Type Of Cluster"*/}
        {/*    variant="filled"*/}
        {/*    error={!!errors.type}*/}
        {/*    helperText={errors.type?.message}*/}
        {/*    {...register("type")}*/}
        {/*    value={watch("type") ?? ""}*/}
        {/*    select*/}
        {/*  >*/}
        {/*    {renderClusterTypeList()}*/}
        {/*  </TextField>*/}
        {/*</Grid>*/}
        <Grid size={12}>
          <TextField
            label="Name"
            variant="filled"
            error={!!errors.name}
            helperText={errors.name?.message}
            {...register("name")}
            fullWidth
          />
        </Grid>
        <Grid size={12}>
          <TextField
            label="URL"
            variant="filled"
            error={!!errors.url}
            helperText={errors.url?.message}
            {...register("url")}
            placeholder="http://example.com"
            fullWidth
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
