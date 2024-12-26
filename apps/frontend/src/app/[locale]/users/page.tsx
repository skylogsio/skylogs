"use client";
import { useState } from "react";

import Table from "@/components/Table";

import CreateUserModal from "./CreateModal";

export default function Users() {
  const [open, setOpen] = useState(false);

  function handleOpen() {
    setOpen(true);
  }

  function handleClose() {
    setOpen(false);
  }

  return (
    <>
      <Table
        title="Users"
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
      <CreateUserModal open={open} onClose={handleClose} />
    </>
  );
}
