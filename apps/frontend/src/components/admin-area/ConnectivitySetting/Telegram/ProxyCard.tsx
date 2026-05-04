import { useState } from "react";

import {
  alpha,
  Button,
  Collapse,
  Grid2,
  Grow,
  IconButton,
  Stack,
  Switch,
  SwitchProps,
  Typography,
  useTheme
} from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { HiPencil, HiTrash } from "react-icons/hi";
import { toast } from "react-toastify";

import type { ITelegramProxy } from "@/@types/settings/telegram";
import { deleteTelegramProxy } from "@/api/setttings/telegram";

interface ProxyCardProps extends Pick<SwitchProps, "checked" | "onChange" | "disabled"> {
  data: ITelegramProxy;
  onEdit: () => void;
  onAfterDelete: () => void;
}

export default function ProxyCard({
  data,
  onAfterDelete,
  onEdit,
  checked,
  onChange,
  disabled
}: ProxyCardProps) {
  const { palette } = useTheme();
  const [showButtons, setShowButtons] = useState<boolean>(false);

  const { mutate: deleteTelegramProxyMutation, isPending } = useMutation({
    mutationFn: () => deleteTelegramProxy(data.id),
    onSuccess: () => {
      toast.success("Proxy Deleted Successfully.");
      onAfterDelete();
    }
  });

  return (
    <Grid2
      onMouseEnter={() => setShowButtons(true)}
      onMouseLeave={() => setShowButtons(false)}
      key={data.id}
      size={4}
      sx={{
        cursor: "default",
        display: "flex",
        flexDirection: "row",
        overflow: "hidden",
        backgroundColor: palette.background.default,
        boxSizing: "border-box",
        borderRadius: "0.7rem"
      }}
    >
      <Collapse orientation="horizontal" in={showButtons}>
        <Button
          variant="contained"
          color="error"
          disabled={isPending}
          onClick={() => deleteTelegramProxyMutation()}
          sx={{
            borderRadius: 0,
            minWidth: 0,
            paddingX: 1.5,
            height: "100%",
            color: palette.error.light,
            backgroundColor: alpha(palette.error.light, 0.1)
          }}
        >
          <HiTrash size="1.4rem" />
        </Button>
      </Collapse>
      <Stack
        flex={1}
        direction="row"
        justifyContent="space-between"
        alignItems="center"
        paddingX={2}
        paddingY={1.5}
      >
        <Stack>
          <Stack direction="row" spacing={1}>
            <Typography variant="body1">{data.name}</Typography>
            <Grow in={showButtons}>
              <IconButton
                onClick={onEdit}
                size="small"
                sx={({ palette }) => ({
                  color: palette.info.light,
                  backgroundColor: alpha(palette.info.light, 0.05)
                })}
              >
                <HiPencil size="1rem" />
              </IconButton>
            </Grow>
          </Stack>
          <Typography variant="subtitle2" color="textSecondary">
            {data.type}://{data.host}:{data.port}
          </Typography>
        </Stack>
        <Switch
          checked={checked}
          disabled={disabled}
          onChange={onChange}
          slotProps={{ input: { "aria-label": `proxy-${data.id}-activation-button` } }}
        />
      </Stack>
    </Grid2>
  );
}
