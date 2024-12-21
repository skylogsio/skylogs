"use client";
import { useState } from "react";

import {
  Button,
  FormControl,
  Grid2 as Grid,
  InputLabel,
  MenuItem,
  Select,
  TextField
} from "@mui/material";
import { z } from "zod";

import ModalContainer from "@/components/Modal";
import Table from "@/components/Table";

const ENDPOINTS_TYPE = ["sms", "telegram", "teams", "call"] as const;

const createEndpointSchema = z.object({
  name: z.string({ required_error: "Name is Required." }).refine((data) => data.trim() !== "", {
    message: "Name is Required."
  }),
  type: z.enum(ENDPOINTS_TYPE, { required_error: "Type is Required." }),
  value: z.string({ required_error: "Value is Required." }).refine((data) => data.trim() !== "", {
    message: "Value is Required."
  })
});

export default function EndPoints() {
  const [open, setOpen] = useState(false);

  function handleClose() {
    setOpen(false);
  }
  function handleOpen() {
    setOpen(true);
  }

  return (
    <>
      <Table
        title="EndPoints"
        url="https://api.escuelajs.co/api/v1/products"
        hasCheckbox
        defaultPage={0}
        defaultPageSize={10}
        columns={[
          { header: "id", accessorKey: "id" },
          { header: "title", accessorKey: "title" },
          { header: "price", accessorKey: "price" }
        ]}
        onCreate={handleOpen}
      />
      <ModalContainer
        title="Create New EndPoint"
        open={open}
        onClose={handleClose}
        disableEscapeKeyDown
      >
        <Grid container spacing={2} width="100%" display="flex" marginTop="2rem">
          <Grid size={6}>
            <TextField label="Name" variant="filled" />
          </Grid>
          <Grid size={6}>
            <FormControl fullWidth variant="filled">
              <InputLabel id="endpoint-label">Label</InputLabel>
              <Select labelId="endpoint-label" id="endpoint-label-id" label="Label">
                {ENDPOINTS_TYPE.map((item) => (
                  <MenuItem
                    key={item}
                    value={item}
                    sx={{ textTransform: item === "sms" ? "uppercase" : "capitalize" }}
                  >
                    {item}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Grid>
          <Grid size={6}>
            <TextField label="ChatID" variant="filled" />
          </Grid>
          <Grid size={6}>
            <TextField label="ThreadID" variant="filled" />
          </Grid>
          <Grid size={12} marginTop="1rem">
            <Button variant="contained" size="large" fullWidth>
              Create
            </Button>
          </Grid>
        </Grid>
      </ModalContainer>
    </>
  );
}
