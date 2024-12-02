import React from "react";

import { Box } from "@mui/material";

import Table from "@/components/Table";

export default async function Home() {
  return (
    <Box width="100%" height="100%" padding="1.7rem">
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
      />
    </Box>
  );
}
