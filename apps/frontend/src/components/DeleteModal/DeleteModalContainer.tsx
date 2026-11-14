import { Button, Grid, Stack, Typography } from "@mui/material";
import { BsExclamationCircle } from "react-icons/bs";
import { HiX } from "react-icons/hi";

import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";
import ModalContainer from "@/components/Modal";
import { mergeSx, mixPrimary, pillSurfaceSx } from "@/components/Wrapper/topBarStyles";

export default function DeleteModalContainer({
  open,
  onClose,
  children,
  onAfterDelete,
  isLoading,
  paperSx,
  contentSx
}: DeleteModalProps) {
  return (
    <ModalContainer open={open} onClose={onClose} width="90%" maxWidth="400px" paperSx={paperSx}>
      <Stack
        spacing={3}
        sx={{
          alignItems: "center"
        }}
      >
        <BsExclamationCircle color="var(--mui-palette-error-main)" size="4rem" />
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
          sx={mergeSx(
            {
              minWidth: "100% !important",
              boxSizing: "border-box",
              padding: 2,
              alignItems: "center",
              bgcolor:
                "color-mix(in srgb, var(--mui-palette-secondary-light) 16%, transparent)",
              border: `1px solid ${mixPrimary(18)}`,
              borderRadius: 3
            },
            (muiTheme) =>
              muiTheme.applyStyles("dark", {
                bgcolor:
                  "color-mix(in srgb, var(--mui-palette-secondary-light) 8%, transparent)",
                border: `1px solid ${mixPrimary(14)}`
              }),
            contentSx
          )}
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
            sx={mergeSx(
              {
                textTransform: "none",
                fontWeight: 600,
                letterSpacing: "0.02em",
                color: "text.primary",
                borderColor: mixPrimary(18),
                "&:hover": {
                  backgroundColor: "#E8E0D4",
                  borderColor: mixPrimary(28)
                },
                "& .MuiButton-startIcon": {
                  color: "text.secondary"
                }
              },
              pillSurfaceSx(),
              (muiTheme) =>
                muiTheme.applyStyles("dark", {
                  borderColor: mixPrimary(14),
                  "&:hover": {
                    backgroundColor: "rgba(255, 255, 255, 0.13)",
                    borderColor: mixPrimary(22)
                  }
                })
            )}
          >
            Cancel
          </Button>
        </Stack>
      </Stack>
    </ModalContainer>
  );
}
