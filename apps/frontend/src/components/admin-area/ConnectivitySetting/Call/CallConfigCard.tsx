"use client";

import { useState } from "react";

import {
  Card,
  CardContent,
  IconButton,
  Menu,
  MenuItem,
  Chip,
  Box,
  Typography,
  useTheme,
  alpha,
  Stack,
  CircularProgress
} from "@mui/material";
import { useMutation } from "@tanstack/react-query";
import { HiDotsHorizontal } from "react-icons/hi";
import { toast } from "react-toastify";

import type { ICallConfig } from "@/@types/admin-area/callConfig";
import { setDefaultCallConfig } from "@/api/admin-area/callConfig";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

export interface CallConfigCardProps {
  config: ICallConfig;
  onEdit?: () => void;
  onDelete?: () => void;
  onSetAsDefault?: () => void;
}

export function CallConfigCard({ config, onEdit, onDelete, onSetAsDefault }: CallConfigCardProps) {
  const { palette, spacing } = useTheme();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const open = Boolean(anchorEl);

  const handleMenuClick = (event: React.MouseEvent<HTMLElement>) => {
    setAnchorEl(event.currentTarget);
  };

  const handleMenuClose = () => {
    setAnchorEl(null);
  };

  const { mutate: setDefaultCallConfigMutation, isPending: isSettingDefault } = useMutation({
    mutationFn: () => setDefaultCallConfig(config.id),
    onSuccess: () => {
      toast.success(`The ${config.name} set as default Call config.`);
      onSetAsDefault?.();
      handleMenuClose();
    }
  });

  const handleEdit = () => {
    onEdit?.();
    handleMenuClose();
  };

  const handleDelete = () => {
    onDelete?.();
    handleMenuClose();
  };

  const CallIcon = ENDPOINT_CONFIG["call"].icon;

  return (
    <Card
      sx={{
        borderRadius: 3,
        border: config.isDefault ? `2px solid ${palette.endpoint.call}` : "none",
        boxShadow: `0 8px 14px ${alpha(config.isDefault ? palette.endpoint.call : palette.grey[500], 0.2)}`,
        position: "relative",
        transition: "all 0.2s ease",
        "&:hover": {
          transform: "scale(1.01)",
          boxShadow: `0 12px 18px ${alpha(config.isDefault ? palette.endpoint.call : palette.grey[500], 0.3)}`
        }
      }}
    >
      <CardContent sx={{ p: 0, "&:last-child": { pb: 0 } }}>
        <Box
          sx={{
            position: "relative",
            display: "flex",
            alignItems: "center",
            gap: 2,
            padding: 3,
            backgroundColor: alpha(palette.endpoint.call, 0.1)
          }}
        >
          <Box
            sx={{
              width: 64,
              height: 64,
              borderRadius: "50%",
              backgroundColor: palette.endpoint.call,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              flexShrink: 0
            }}
          >
            <CallIcon size={32} color="white" />
          </Box>
          <Box sx={{ flex: 1 }}>
            <Typography variant="h6" fontWeight="bold" sx={{ marginBottom: 0.5 }}>
              {config.name}
            </Typography>
            {config.isDefault && (
              <Chip
                label="Default"
                size="small"
                sx={{
                  borderRadius: 2.5,
                  backgroundColor: palette.endpoint.call,
                  color: "white",
                  fontWeight: "bold"
                }}
              />
            )}
          </Box>
          <IconButton
            onClick={handleMenuClick}
            size="small"
            sx={{
              position: "absolute",
              top: spacing(1.5),
              right: spacing(1.5),
              color: palette.grey[700],
              backgroundColor: alpha(palette.endpoint.call, 0.1),
              transition: "all 0.2s linear",
              "&:hover": {
                backgroundColor: alpha(palette.endpoint.call, 0.2)
              }
            }}
          >
            <HiDotsHorizontal size={20} />
          </IconButton>
        </Box>
        <Stack padding={3} spacing={2}>
          <Box
            sx={{
              backgroundColor: palette.background.default,
              borderRadius: 4,
              py: 1.5,
              px: 2,
              wordBreak: "break-all"
            }}
          >
            <Typography variant="subtitle2" fontWeight="bold">
              Api Token
            </Typography>
            <Typography variant="caption" color="text.secondary">
              {config.apiToken}
            </Typography>
          </Box>
        </Stack>
      </CardContent>
      <Menu
        anchorEl={anchorEl}
        open={open}
        onClose={handleMenuClose}
        anchorOrigin={{
          vertical: "bottom",
          horizontal: "right"
        }}
        transformOrigin={{
          vertical: "top",
          horizontal: "right"
        }}
      >
        <MenuItem onClick={handleEdit}>Edit</MenuItem>
        <MenuItem onClick={handleDelete} sx={{ color: palette.error.main }}>
          Delete
        </MenuItem>
        {!config.isDefault && (
          <MenuItem onClick={() => setDefaultCallConfigMutation()} disabled={isSettingDefault}>
            Set As Default
            {isSettingDefault && <CircularProgress size={16} sx={{ marginLeft: 2 }} />}
          </MenuItem>
        )}
      </Menu>
    </Card>
  );
}
