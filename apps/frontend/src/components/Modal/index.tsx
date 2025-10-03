"use client";

import { Box, IconButton, Modal, Paper, Typography, Fade, backdropClasses } from "@mui/material";
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
  onClose
}: ModalContainerProps) {
  return (
    <Modal
      open={open}
      onClose={disableAccidentalClose ? undefined : onClose}
      aria-labelledby="modal-modal-title"
      aria-describedby="modal-modal-description"
      sx={{
        [`& .${backdropClasses.root}`]: {
          backdropFilter: "blur(4px)"
        }
      }}
    >
      <Fade in={open}>
        <Box
          width={width}
          maxWidth={maxWidth}
          sx={{
            position: "absolute",
            top: "50%",
            left: "50%",
            transform: "translate(-50%,-50%)"
          }}
        >
          <Paper sx={{ padding, borderRadius: "0.7rem", boxShadow: "none" }}>
            <Box
              width="100%"
              display="flex"
              flexDirection="row-reverse"
              justifyContent="space-between"
              alignItems="center"
            >
              <IconButton
                onClick={() => onClose?.()}
                type="button"
                sx={{ margin: "-0.5rem -0.5rem 0 auto" }}
              >
                <HiOutlineX />
              </IconButton>
              {title && (
                <Typography variant="h6" fontWeight="bold">
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
