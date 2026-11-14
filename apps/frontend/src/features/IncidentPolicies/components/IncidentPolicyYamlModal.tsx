"use client";

import { useRef, useState } from "react";

import {
  Alert,
  Button,
  Checkbox,
  FormControlLabel,
  Stack,
  TextField,
  Typography,
  useTheme
} from "@mui/material";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "react-toastify";

import GradientSubmitButton from "@/components/GradientSubmitButton";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

import {
  importIncidentPolicyYaml,
  validateIncidentPolicyYaml
} from "../incident-policy.api";
import type { IIncidentPolicyYamlResult } from "../incident-policy.type";

import IncidentPolicyModalBody from "./IncidentPolicyModalBody";

type IncidentPolicyYamlModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  onSubmit: () => void;
};

const MAX_YAML_BYTES = 512 * 1024;

function summarizeResult(result: IIncidentPolicyYamlResult): string {
  const parts: string[] = [];
  if (result.created?.length) parts.push(`${result.created.length} created`);
  if (result.updated?.length) parts.push(`${result.updated.length} updated`);
  if (result.unchanged?.length) parts.push(`${result.unchanged.length} unchanged`);
  return parts.join(", ") || "No changes";
}

export default function IncidentPolicyYamlModal({
  open,
  onClose,
  onSubmit
}: IncidentPolicyYamlModalProps) {
  const theme = useTheme();
  const { isDark } = useCurrentTheme();
  const queryClient = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [yaml, setYaml] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [dryRun, setDryRun] = useState(false);
  const [result, setResult] = useState<IIncidentPolicyYamlResult | null>(null);

  function resetState() {
    setYaml("");
    setFile(null);
    setDryRun(false);
    setResult(null);
    if (fileInputRef.current) fileInputRef.current.value = "";
  }

  function handleClose() {
    resetState();
    onClose?.();
  }

  function buildBody(): { yaml: string; dryRun: boolean } | FormData {
    if (file) {
      const formData = new FormData();
      formData.append("file", file);
      formData.append("dryRun", dryRun ? "1" : "0");
      return formData;
    }
    return { yaml, dryRun };
  }

  const { mutate: validateMutation, isPending: isValidating } = useMutation({
    mutationFn: () => validateIncidentPolicyYaml(buildBody()),
    onSuccess: (response) => {
      setResult(response);
      if (response.valid === false || (response.errors && response.errors.length > 0)) {
        toast.error("YAML validation failed.");
        return;
      }
      if (response.valid === true) {
        toast.success("YAML is valid.");
        return;
      }
      if (response.message) toast.error(response.message);
    }
  });

  const { mutate: importMutation, isPending: isImporting } = useMutation({
    mutationFn: () => importIncidentPolicyYaml(buildBody()),
    onSuccess: (response) => {
      setResult(response);
      if (response.valid === false || (response.errors && response.errors.length > 0)) {
        toast.error("YAML import failed.");
        return;
      }
      if (response.message && response.status === false) {
        toast.error(response.message);
        return;
      }
      void queryClient.invalidateQueries({ queryKey: ["incident-policy"] });
      toast.success(
        dryRun ? `Dry run: ${summarizeResult(response)}` : `Imported: ${summarizeResult(response)}`
      );
      if (!dryRun) {
        onSubmit();
        handleClose();
      }
    }
  });

  function handleFileChange(selected: File | null) {
    if (!selected) {
      setFile(null);
      return;
    }
    if (selected.size > MAX_YAML_BYTES) {
      toast.error("YAML file must be 512 KB or smaller.");
      if (fileInputRef.current) fileInputRef.current.value = "";
      return;
    }
    const lower = selected.name.toLowerCase();
    if (!lower.endsWith(".yaml") && !lower.endsWith(".yml")) {
      toast.error("Only .yaml or .yml files are allowed.");
      if (fileInputRef.current) fileInputRef.current.value = "";
      return;
    }
    setFile(selected);
  }

  const canSubmit = Boolean(file) || Boolean(yaml.trim());
  const isPending = isValidating || isImporting;

  return (
    <ModalContainer
      title="Import Incident Policy YAML"
      open={open}
      onClose={handleClose}
      disableEscapeKeyDown
      maxWidth={720}
      paperSx={getGlassCardSx(theme, isDark)}
    >
      <IncidentPolicyModalBody>
        <Stack spacing={2}>
          <TextField
            label="YAML"
            variant="filled"
            multiline
            minRows={10}
            fullWidth
            value={yaml}
            onChange={(event) => setYaml(event.target.value)}
            disabled={Boolean(file)}
            helperText={file ? "Clear the file to paste YAML instead." : undefined}
          />
          <Stack direction="row" spacing={1.5} sx={{ alignItems: "center" }}>
            <Button
              variant="outlined"
              component="label"
              sx={{ textTransform: "none", fontWeight: 600 }}
            >
              {file ? file.name : "Choose .yaml file"}
              <input
                ref={fileInputRef}
                hidden
                type="file"
                accept=".yaml,.yml,text/yaml,application/x-yaml"
                onChange={(event) => handleFileChange(event.target.files?.[0] ?? null)}
              />
            </Button>
            {file && (
              <Button
                size="small"
                onClick={() => handleFileChange(null)}
                sx={{ textTransform: "none" }}
              >
                Clear file
              </Button>
            )}
          </Stack>
          <FormControlLabel
            control={
              <Checkbox checked={dryRun} onChange={(_, checked) => setDryRun(checked)} />
            }
            label="Dry run (do not write)"
          />

          {result?.errors && result.errors.length > 0 && (
            <Alert severity="error">
              <Stack spacing={0.5}>
                {result.errors.map((error, index) => (
                  <Typography key={`${error.path}-${index}`} variant="body2">
                    {error.path ? `${error.path}: ` : ""}
                    {error.message}
                  </Typography>
                ))}
              </Stack>
            </Alert>
          )}

          {result && result.valid !== false && (!result.errors || result.errors.length === 0) && (
            <Alert severity="success">{summarizeResult(result)}</Alert>
          )}

          <Stack direction="row" spacing={1.5}>
            <Button
              fullWidth
              variant="outlined"
              disabled={!canSubmit || isPending}
              onClick={() => validateMutation()}
              sx={{ textTransform: "none", fontWeight: 600 }}
            >
              Validate
            </Button>
            <GradientSubmitButton
              fullWidth
              loading={isImporting}
              disabled={!canSubmit || isPending}
              onClick={() => importMutation()}
            >
              {dryRun ? "Dry Run Import" : "Import"}
            </GradientSubmitButton>
          </Stack>
        </Stack>
      </IncidentPolicyModalBody>
    </ModalContainer>
  );
}
