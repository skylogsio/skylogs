"use client";
import { useRef, useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  alpha,
  Box,
  Button,
  Chip,
  Grid2 as Grid,
  IconButton,
  MenuItem,
  TextField
} from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { HiOutlinePencil, HiOutlineTrash } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import { createEndpoint } from "@/api/endpoint";
import ModalContainer from "@/components/Modal";
import Table from "@/components/Table";
import { type TableComponentRef } from "@/components/Table/types";

const ENDPOINTS_TYPE = ["sms", "telegram", "teams", "call"] as const;

const ENDPOINT_TYPE_CHIP = {
  sms: { title: "SMS", color: "#13C82B" },
  telegram: { title: "Telegram", color: "#4880FF" },
  teams: { title: "Teams", color: "#454DB3" },
  call: { title: "Call", color: "#B65DFE" }
};

const createEndpointSchema = z.object({
  name: z.string({ required_error: "Name is Required." }).refine((data) => data.trim() !== "", {
    message: "Name is Required."
  }),
  type: z.enum(ENDPOINTS_TYPE, {
    required_error: "Type is Required.",
    message: "Type is Required."
  }),
  value: z.string({ required_error: "Value is Required." }).refine((data) => data.trim() !== "", {
    message: "Value is Required."
  }),
  threadId: z.optional(z.string())
});

type EndpointFormType = z.infer<typeof createEndpointSchema>;

function ActionButtons({ onEdit = () => {} }) {
  return (
    <Box>
      <IconButton
        onClick={onEdit}
        sx={({ palette }) => ({
          color: palette.info.light,
          backgroundColor: alpha(palette.info.light, 0.05)
        })}
      >
        <HiOutlinePencil size="1.4rem" />
      </IconButton>
      <IconButton
        sx={({ palette }) => ({
          color: palette.error.light,
          backgroundColor: alpha(palette.error.light, 0.05),
          marginLeft: "0.5rem"
        })}
      >
        <HiOutlineTrash size="1.4rem" />
      </IconButton>
    </Box>
  );
}

export default function EndPoints() {
  const {
    register,
    handleSubmit,
    watch,
    reset,
    formState: { errors }
  } = useForm<EndpointFormType>({
    resolver: zodResolver(createEndpointSchema),
    defaultValues: {
      name: "",
      type: undefined,
      value: ""
    }
  });
  const tableRef = useRef<TableComponentRef>(null);
  const [open, setOpen] = useState(false);

  function handleClose() {
    setOpen(false);
    reset();
  }

  function handleOpen() {
    setOpen(true);
  }

  function handleEdit(data: EndpointFormType) {
    reset(data);
    handleOpen();
  }

  const { mutate, isPending } = useMutation({
    mutationFn: (data) => createEndpoint(data),
    onSuccess: () => {
      toast.success("Successfully created Endpoint!");
      tableRef.current?.refreshData();
      handleClose();
    }
    // onError: () => {}
  });

  function handleSubmitForm(data: EndpointFormType) {
    console.log(data);
    const temp = { ...data, metadata: {} };
    if (temp.threadId !== undefined) {
      delete temp.threadId;
      temp.metadata = { threadId: data.threadId };
    }
    mutate(temp as unknown as void);
  }

  return (
    <>
      <Table<EndpointFormType>
        ref={tableRef}
        title="EndPoints"
        url={`${process.env.NEXT_PUBLIC_BASE_URL}endpoint`}
        defaultPage={1}
        defaultPageSize={10}
        columns={[
          { header: "Row", accessorFn: (_, index) => index },
          { header: "Name", accessorKey: "name" },
          {
            header: "Type",
            accessorKey: "type",
            cell: ({ cell }) => (
              <Chip
                sx={{
                  backgroundColor: alpha(
                    ENDPOINT_TYPE_CHIP[cell.getValue() as keyof typeof ENDPOINT_TYPE_CHIP].color,
                    0.1
                  ),
                  color:
                    ENDPOINT_TYPE_CHIP[cell.getValue() as keyof typeof ENDPOINT_TYPE_CHIP].color,
                  borderRadius: "0.4rem"
                }}
                label={ENDPOINT_TYPE_CHIP[cell.getValue() as keyof typeof ENDPOINT_TYPE_CHIP].title}
              />
            )
          },
          { header: "Value", accessorKey: "value" },
          { header: "Created At", accessorKey: "" },
          {
            header: "Action",
            cell: ({ row }) => <ActionButtons onEdit={() => handleEdit(row.original)} />
          }
        ]}
        onCreate={handleOpen}
      />
      <ModalContainer
        title="Create New EndPoint"
        open={open}
        onClose={handleClose}
        disableEscapeKeyDown
      >
        <Grid
          component="form"
          onSubmit={handleSubmit(handleSubmitForm, (error) => console.log(error))}
          container
          spacing={2}
          width="100%"
          display="flex"
          marginTop="2rem"
        >
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
            <TextField
              label="Type"
              variant="filled"
              error={!!errors.type}
              helperText={errors.type?.message}
              {...register("type")}
              value={watch("type") ?? ""}
              select
            >
              {ENDPOINTS_TYPE.map((item) => (
                <MenuItem
                  key={item}
                  value={item}
                  sx={{ textTransform: item === "sms" ? "uppercase" : "capitalize" }}
                >
                  {item}
                </MenuItem>
              ))}
            </TextField>
          </Grid>
          <Grid size={watch("type") === "telegram" ? 6 : 12}>
            <TextField
              label={watch("type") === "telegram" ? "ChatID" : "Value"}
              variant="filled"
              error={!!errors.value}
              helperText={errors.value?.message}
              {...register("value")}
            />
          </Grid>
          {watch("type") === "telegram" && (
            <Grid size={6}>
              <TextField
                label="ThreadID"
                variant="filled"
                error={!!errors.threadId}
                helperText={errors.threadId?.message}
                {...register("threadId")}
              />
            </Grid>
          )}
          <Grid size={12} marginTop="1rem">
            <Button disabled={isPending} type="submit" variant="contained" size="large" fullWidth>
              Create
            </Button>
          </Grid>
        </Grid>
      </ModalContainer>
    </>
  );
}
