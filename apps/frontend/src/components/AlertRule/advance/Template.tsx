import { useParams } from "next/navigation";
import React, { useEffect } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Autocomplete, Box, Button, Chip, Grid, TextField } from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm, Controller } from "react-hook-form";
import { toast } from "react-toastify";
import * as z from "zod";

import {
  addBehaviorRuleToAlertRule,
  editBehaviorRuleToAlertRule,
  getAlertRuleCreateData
} from "@/api/alertRule";
import ModalContainer from "@/components/Modal";

import type { TemplateItem } from "./BehaviorRuleType";

const templateSchema = z.object({
  name: z.string().min(1, "Name is required"),
  endpointIds: z.array(z.string()).min(1, "At least one Endpoint is required"),
  template: z.string().min(1, "Template is required")
});

type TemplateFormData = z.infer<typeof templateSchema>;

interface TemplateModalProps {
  open: boolean;
  onClose: () => void;
  data: "NEW" | TemplateItem;
}

const defaultValues: TemplateFormData = {
  name: "",
  endpointIds: [],
  template: ""
};

const TemplateModal: React.FC<TemplateModalProps> = ({ open, onClose, data }) => {
  const queryClient = useQueryClient();
  const { alertId } = useParams<{ alertId: string }>();
  const ruleId = data !== "NEW" ? (data as TemplateFormData & { id: string }).id : "";

  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
    reset
  } = useForm<TemplateFormData>({
    resolver: zodResolver(templateSchema),
    defaultValues
  });

  const { data: endpointsData } = useQuery({
    queryKey: ["alert-rule-create-data"],
    queryFn: () => getAlertRuleCreateData()
  });

  const handleClose = () => {
    reset(defaultValues);
    onClose();
    queryClient.invalidateQueries({ queryKey: ["get-behavior-rule"] });
  };

  const { mutate: addTemplateRule } = useMutation({
    mutationFn: (body: TemplateFormData) => addBehaviorRuleToAlertRule(alertId, body),
    onSuccess: () => {
      toast.success("Template Rule Created Successfully.");
      handleClose();
    }
  });

  const { mutate: editTemplateRule } = useMutation({
    mutationFn: (body: TemplateFormData) => editBehaviorRuleToAlertRule(alertId, ruleId, body),
    onSuccess: () => {
      toast.success("Template Rule Updated Successfully.");
      handleClose();
    }
  });

  const endpoints = endpointsData?.endpoints ?? [];

  const handleFormSubmit = (formData: TemplateFormData) => {
    const body = { ...formData, type: "template" };
    if (data === "NEW") {
      addTemplateRule(body);
    } else {
      editTemplateRule(body);
    }
  };

  useEffect(() => {
    if (data === "NEW") {
      reset(defaultValues);
    } else {
      reset(data);
    }
  }, [data, reset]);

  return (
    <ModalContainer
      open={open}
      onClose={handleClose}
      title="Template Rule"
      width="90%"
      maxWidth="600px"
    >
      <form onSubmit={handleSubmit(handleFormSubmit)}>
        <Box sx={{ mt: 2 }}>
          <Grid container spacing={2}>
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
              <Controller
                control={control}
                name="endpointIds"
                render={({ field }) => {
                  const selectedEndpoints = endpoints.filter((ep) => field.value?.includes(ep.id));

                  return (
                    <Autocomplete
                      multiple
                      options={endpoints}
                      getOptionLabel={(option) => option.name}
                      value={selectedEndpoints}
                      onChange={(_, newValue) => {
                        field.onChange(newValue.map((ep) => ep.id));
                      }}
                      isOptionEqualToValue={(option, value) => option.id === value.id}
                      renderValue={(value, getItemProps) =>
                        value.map((option, index) => {
                          const { key, ...itemProps } = getItemProps({ index });
                          return <Chip key={key} label={option.name} size="small" {...itemProps} />;
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
                          label="Endpoints"
                          error={!!errors.endpointIds}
                          helperText={errors.endpointIds?.message as string}
                        />
                      )}
                    />
                  );
                }}
              />
            </Grid>
            <Grid size={12}>
              <TextField
                label="Template"
                variant="filled"
                multiline
                rows={4}
                error={!!errors.template}
                helperText={errors.template?.message}
                {...register("template")}
              />
            </Grid>
          </Grid>

          <Box sx={{ display: "flex", justifyContent: "flex-end", gap: 2, mt: 3 }}>
            <Button onClick={handleClose} variant="outlined" color="primary">
              Cancel
            </Button>
            <Button type="submit" variant="contained" color="primary">
              {data === "NEW" ? "Create" : "Update"}
            </Button>
          </Box>
        </Box>
      </form>
    </ModalContainer>
  );
};

export default TemplateModal;
