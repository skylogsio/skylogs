"use client";

import {
  Box,
  IconButton,
  Modal,
  Paper,
  Typography,
  Fade,
  backdropClasses,
  ModalProps
} from "@mui/material";
import { HiOutlineX } from "react-icons/hi";

import { type ModalContainerProps } from "./types";

export default function ModalContainer({
  open,
  width = "100%",
  maxWidth = 600,
  padding = "1.6rem",
  title,
  children,
  disableAccidentalClose,
  disableEscapeKeyDown,
  onClose
}: ModalContainerProps) {
  const handleClose: ModalProps["onClose"] = (event, reason) => {
    if (disableAccidentalClose) return;
    if (disableEscapeKeyDown && reason === "escapeKeyDown") return;
    onClose?.();
  };

  return (
    <Modal
      open={open}
      onClose={handleClose}
      aria-labelledby="modal-title"
      aria-describedby="modal-description"
      sx={{
        [`& .${backdropClasses.root}`]: {
          backdropFilter: "blur(4px)"
        }
      }}
    >
      <Fade in={open}>
        <Box
          sx={{
            width: width,
            maxWidth: maxWidth,
            position: "absolute",
            top: "50%",
            left: "50%",
            transform: "translate(-50%,-50%)"
          }}
        >
          <Paper sx={{ padding, borderRadius: 1, boxShadow: "none" }}>
            <Box
              sx={{
                width: 1,
                display: "flex",
                flexDirection: "row-reverse",
                justifyContent: "space-between",
                alignItems: "center"
              }}
            >
              <IconButton
                onClick={() => onClose?.()}
                type="button"
                sx={{ margin: "-0.5rem -0.5rem 0 auto" }}
              >
                <HiOutlineX />
              </IconButton>
              {title && (
                <Typography
                  variant="h6"
                  sx={{
                    fontWeight: "bold"
                  }}
                >
                  {title}
                </Typography>
              )}
            </Box>
            {children}
          </Paper>
        </Box>
      </Fade>
    </Modal>
  );
}
