import Table from "@/components/Table";

export default async function Home() {
  return (
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
  );
}
