import { alpha, Button, Grid2 as Grid, Stack, Typography, useTheme } from "@mui/material";
import { BsExclamationCircle } from "react-icons/bs";
import { HiX } from "react-icons/hi";

import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import ModalContainer from "@/components/Modal";

export default function DeleteModalContainer({
  open,
  onClose,
  children,
  onAfterDelete,
  isLoading
}: DeleteModalProps) {
  const { palette } = useTheme();
  return (
    <ModalContainer open={open} onClose={onClose} width="90%" maxWidth="400px">
      <Stack spacing={3} alignItems="center">
        <BsExclamationCircle color={palette.error.main} size="4rem" />
        <Typography variant="h5" fontWeight="bold" component="div" align="center">
          Are you sure?
        </Typography>
        <Typography variant="body2" component="p" align="center">
          Do you really want to delete this record?
          <br />
          This process can not be undone.
        </Typography>
        <Grid
          minWidth="100%"
          boxSizing="border-box"
          container
          padding={2}
          spacing={1}
          sx={{ minWidth: "100% !important", boxSizing: "border-box" }}
          alignItems="center"
          bgcolor={alpha(palette.secondary.light, 0.3)}
          borderRadius={3}
        >
          {children}
        </Grid>
        <Stack width="100%" direction="row-reverse" justifyContent="flex-start" spacing={2}>
          <Button
            fullWidth
            disabled={isLoading}
            variant="contained"
            color="error"
            onClick={onAfterDelete}
          >
            Yes, I&#39;m Sure.
          </Button>
          <Button
            fullWidth
            disabled={isLoading}
            variant="outlined"
            onClick={onClose}
            color="secondary"
            startIcon={<HiX />}
          >
            Cancel
          </Button>
        </Stack>
      </Stack>
    </ModalContainer>
  );
}
