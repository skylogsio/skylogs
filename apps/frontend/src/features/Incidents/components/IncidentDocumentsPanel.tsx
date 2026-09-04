"use client";

import { useState } from "react";

import {
  Box,
  Button,
  CircularProgress,
  IconButton,
  MenuItem,
  Stack,
  TextField,
  Typography,
  alpha,
  useTheme
} from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { HiExternalLink, HiTrash } from "react-icons/hi";
import { toast } from "react-toastify";

import GradientSubmitButton from "@/components/GradientSubmitButton";
import { useCurrentTheme } from "@/hooks";

import {
  createIncidentDocumentFile,
  createIncidentDocumentLink,
  deleteIncidentDocument,
  getIncidentDocumentDownloadUrl,
  listIncidentDocuments
} from "../incident.api";
import {
  DOCUMENT_TYPES,
  type DocumentAttachableType,
  type IIncident,
  type IncidentDocumentType
} from "../incident.type";
import { formatIncidentDateTime } from "../incident.utils";

type IncidentDocumentsPanelProps = {
  incident: IIncident;
};

const MAX_FILE_BYTES = 20 * 1024 * 1024;

export default function IncidentDocumentsPanel({ incident }: IncidentDocumentsPanelProps) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();
  const queryClient = useQueryClient();
  const canEdit = incident.canEdit;

  const [mode, setMode] = useState<"link" | "file">("link");
  const [externalUrl, setExternalUrl] = useState("");
  const [name, setName] = useState("");
  const [type, setType] = useState<IncidentDocumentType>("other");
  const [description, setDescription] = useState("");
  const [attachableType, setAttachableType] = useState<DocumentAttachableType>("incident");
  const [file, setFile] = useState<File | null>(null);
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  const hasPostMortem = Boolean(incident.postMortem);

  const { data: documents = [], isPending } = useQuery({
    queryKey: ["incident-documents", incident.id],
    queryFn: () => listIncidentDocuments(incident.id)
  });

  function invalidate() {
    void queryClient.invalidateQueries({ queryKey: ["incident-documents", incident.id] });
    void queryClient.invalidateQueries({ queryKey: ["incident", incident.id] });
  }

  function resetForm() {
    setExternalUrl("");
    setName("");
    setType("other");
    setDescription("");
    setAttachableType("incident");
    setFile(null);
  }

  const { mutate: createLink, isPending: isCreatingLink } = useMutation({
    mutationFn: () =>
      createIncidentDocumentLink(incident.id, {
        externalUrl: externalUrl.trim(),
        ...(name.trim() ? { name: name.trim() } : {}),
        type,
        ...(description.trim() ? { description: description.trim() } : {}),
        attachableType
      }),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Document added.");
        resetForm();
        invalidate();
        return;
      }
      toast.error(response.message);
    }
  });

  const { mutate: createFile, isPending: isCreatingFile } = useMutation({
    mutationFn: () => {
      const formData = new FormData();
      formData.append("file", file!);
      formData.append("type", type);
      if (description.trim()) formData.append("description", description.trim());
      formData.append("attachableType", attachableType);
      return createIncidentDocumentFile(incident.id, formData);
    },
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Document uploaded.");
        resetForm();
        invalidate();
        return;
      }
      toast.error(response.message);
    }
  });

  const { mutate: removeDocument, isPending: isDeleting } = useMutation({
    mutationFn: (documentId: string) => deleteIncidentDocument(incident.id, documentId),
    onSuccess: (response) => {
      if (response.status) {
        toast.success("Document deleted.");
        invalidate();
        return;
      }
      toast.error(response.message);
    }
  });

  async function handleDownload(documentId: string) {
    try {
      setDownloadingId(documentId);
      const result = await getIncidentDocumentDownloadUrl(incident.id, documentId);
      if (!result.url) {
        toast.error("Download URL missing.");
        return;
      }
      window.open(result.url, "_blank", "noopener,noreferrer");
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Failed to download.");
    } finally {
      setDownloadingId(null);
    }
  }

  function handleAdd() {
    if (mode === "link") {
      if (!externalUrl.trim()) {
        toast.error("External URL is required.");
        return;
      }
      createLink();
      return;
    }
    if (!file) {
      toast.error("Choose a file to upload.");
      return;
    }
    if (file.size > MAX_FILE_BYTES) {
      toast.error("File must be 20 MB or smaller.");
      return;
    }
    createFile();
  }

  return (
    <Stack spacing={2}>
      {isPending ? (
        <Stack sx={{ alignItems: "center", py: 4 }}>
          <CircularProgress size={28} />
        </Stack>
      ) : documents.length === 0 ? (
        <Typography variant="body2" sx={{ color: "text.secondary" }}>
          No documents yet.
        </Typography>
      ) : (
        <Stack spacing={1}>
          {documents.map((doc) => (
            <Box
              key={doc.id}
              sx={{
                p: 1.5,
                borderRadius: 2,
                border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`,
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                gap: 1
              }}
            >
              <Stack spacing={0.25} sx={{ minWidth: 0 }}>
                <Typography sx={{ fontWeight: 600 }} noWrap>
                  {doc.name || doc.externalUrl || doc.type}
                </Typography>
                <Typography variant="caption" sx={{ color: "text.secondary" }}>
                  {doc.type}
                  {doc.attachableType ? ` · ${doc.attachableType}` : ""}
                  {doc.createdAt ? ` · ${formatIncidentDateTime(doc.createdAt)}` : ""}
                </Typography>
              </Stack>
              <Stack direction="row" spacing={0.5}>
                <IconButton
                  aria-label="Open document"
                  onClick={() => void handleDownload(doc.id)}
                  disabled={downloadingId === doc.id}
                >
                  <HiExternalLink />
                </IconButton>
                {canEdit && (
                  <IconButton
                    aria-label="Delete document"
                    color="error"
                    disabled={isDeleting}
                    onClick={() => removeDocument(doc.id)}
                  >
                    <HiTrash />
                  </IconButton>
                )}
              </Stack>
            </Box>
          ))}
        </Stack>
      )}

      {canEdit && (
        <Stack
          spacing={1.5}
          sx={{
            p: 1.5,
            borderRadius: 2,
            border: `1px dashed ${alpha(palette.primary.main, isDark ? 0.2 : 0.28)}`
          }}
        >
          <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
            Add document
          </Typography>
          <Stack direction="row" spacing={1}>
            <Button
              size="small"
              variant={mode === "link" ? "contained" : "outlined"}
              onClick={() => setMode("link")}
              sx={{ textTransform: "none" }}
            >
              Link
            </Button>
            <Button
              size="small"
              variant={mode === "file" ? "contained" : "outlined"}
              onClick={() => setMode("file")}
              sx={{ textTransform: "none" }}
            >
              File
            </Button>
          </Stack>
          {mode === "link" ? (
            <>
              <TextField
                label="External URL"
                variant="filled"
                value={externalUrl}
                onChange={(event) => setExternalUrl(event.target.value)}
                fullWidth
              />
              <TextField
                label="Name"
                variant="filled"
                value={name}
                onChange={(event) => setName(event.target.value)}
                fullWidth
              />
            </>
          ) : (
            <Button variant="outlined" component="label" sx={{ textTransform: "none" }}>
              {file ? file.name : "Choose file (max 20 MB)"}
              <input
                hidden
                type="file"
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
              />
            </Button>
          )}
          <TextField
            select
            label="Type"
            variant="filled"
            value={type}
            onChange={(event) => setType(event.target.value as IncidentDocumentType)}
            fullWidth
          >
            {DOCUMENT_TYPES.map((docType) => (
              <MenuItem key={docType} value={docType}>
                {docType}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            select
            label="Attach to"
            variant="filled"
            value={attachableType}
            onChange={(event) =>
              setAttachableType(event.target.value as DocumentAttachableType)
            }
            fullWidth
            helperText={
              !hasPostMortem ? "Postmortem attachment is available after a postmortem exists." : undefined
            }
          >
            <MenuItem value="incident">Incident</MenuItem>
            <MenuItem value="postMortem" disabled={!hasPostMortem}>
              Postmortem
            </MenuItem>
          </TextField>
          <TextField
            label="Description"
            variant="filled"
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            fullWidth
          />
          <GradientSubmitButton
            fullWidth
            loading={isCreatingLink || isCreatingFile}
            onClick={handleAdd}
          >
            Add
          </GradientSubmitButton>
        </Stack>
      )}
    </Stack>
  );
}
