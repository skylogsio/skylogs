import { Stack, Typography } from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { toast } from "react-toastify";

import type { IDataSource } from "@/@types/dataSource";
import { deleteDataSource } from "@/api/dataSource";
import ConnectionStatus from "@/components/ConnectionStatus";
import DataSourceType from "@/components/DataSource/DataSourceType";
import DeleteModalContainer from "@/components/DeleteModal/DeleteModalContainer";
import type { DeleteModalProps } from "@/components/DeleteModal/DeleteModalTypes";

export default function DeleteDataSourceModal({
  data,
  onAfterDelete,
  ...props
}: DeleteModalProps & { data: IDataSource }) {
  const { id, name, type } = data;

  const { mutate: deleteDataSourceMutation, isPending } = useMutation({
    mutationFn: () => deleteDataSource(id),
    onSuccess() {
      onAfterDelete?.();
      toast.success("DataSource Deleted Successfully.");
    }
  });

  return (
    <DeleteModalContainer {...props} onAfterDelete={deleteDataSourceMutation} isLoading={isPending}>
      <Stack spacing={1}>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Name:
          </Typography>
          <Typography variant="subtitle2" color="text.secondary">
            {name}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Type:
          </Typography>
          <DataSourceType
            type={type}
            iconSize="1.3rem"
            typographyProps={{ variant: "subtitle2", color: "text.secondary" }}
          />
        </Stack>
        <Stack direction="row" spacing={1}>
          <Typography variant="subtitle2" color="text.secondary" fontWeight="bold">
            Status:
          </Typography>
          <ConnectionStatus dataSourceId={id} size="small" />
        </Stack>
      </Stack>
    </DeleteModalContainer>
  );
}
