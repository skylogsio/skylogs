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
        url={`${process.env.NEXT_PUBLIC_BASE_URL}users`}
        defaultPage={1}
        defaultPageSize={10}
        columns={[
          { header: "Row", accessorFn: (_, index) => index },
          { header: "Username", accessorKey: "name" },
          { header: "Full Name", accessorKey: "price" },
          { header: "Role", accessorKey: "price" },
          { header: "Created At", accessorKey: "price" },
          { header: "Updated At", accessorKey: "price" }
        ]}
        onCreate={handleOpen}
      />
      <CreateUserModal open={open} onClose={handleClose} />
    </>
  );
}
