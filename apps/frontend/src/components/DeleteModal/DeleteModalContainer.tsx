import { alpha, Button, Grid, Stack, Typography, useTheme } from "@mui/material";
import { BsExclamationCircle } from "react-icons/bs";
import { HiX } from "react-icons/hi";

import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import ModalContainer from "@/components/Modal";
import { useCurrentTheme } from "@/hooks";

export default function DeleteModalContainer({
  open,
  onClose,
  children,
  onAfterDelete,
  isLoading,
  paperSx,
  contentSx
}: DeleteModalProps) {
  const { palette } = useTheme();
  const { isDark } = useCurrentTheme();
  return (
    <ModalContainer open={open} onClose={onClose} width="90%" maxWidth="400px" paperSx={paperSx}>
      <Stack
        spacing={3}
        sx={{
          alignItems: "center"
        }}
      >
        <BsExclamationCircle color={palette.error.main} size="4rem" />
        <Typography
          variant="h5"
          component="div"
          sx={{
            fontWeight: "bold",
            textAlign: "center"
          }}
        >
          Are you sure?
        </Typography>
        <Typography variant="body2" component="p" sx={{ textAlign: "center" }}>
          Do you really want to delete this record?
          <br />
          This process can not be undone.
        </Typography>
        <Grid
          container
          spacing={1}
          sx={{
            minWidth: "100% !important",
            boxSizing: "border-box",
            padding: 2,
            alignItems: "center",
            bgcolor: alpha(palette.secondary.light, isDark ? 0.08 : 0.16),
            border: `1px solid ${alpha(palette.primary.main, isDark ? 0.14 : 0.18)}`,
            borderRadius: 3,
            ...contentSx
          }}
        >
          {children}
        </Grid>
        <Stack
          direction="row-reverse"
          spacing={2}
          sx={{
            width: "100%",
            justifyContent: "flex-start"
          }}
        >
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
            startIcon={<HiX />}
            sx={{
              textTransform: "none",
              fontWeight: 600,
              letterSpacing: "0.02em",
              color: palette.text.primary,
              backgroundColor: isDark ? "rgba(255, 255, 255, 0.09)" : "#F1EBE1",
              borderColor: alpha(palette.primary.main, isDark ? 0.14 : 0.18),
              "&:hover": {
                backgroundColor: isDark ? "rgba(255, 255, 255, 0.13)" : "#E8E0D4",
                borderColor: alpha(palette.primary.main, isDark ? 0.22 : 0.28)
              },
              "& .MuiButton-startIcon": {
                color: palette.text.secondary
              }
            }}
          >
            Cancel
          </Button>
        </Stack>
      </Stack>
    </ModalContainer>
  );
}
