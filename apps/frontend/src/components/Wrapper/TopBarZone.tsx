"use client";

import { useMemo, useState } from "react";

import {
  Box,
  IconButton,
  ListItemIcon,
  ListItemText,
  Menu,
  MenuItem,
  Skeleton,
  Stack,
  Typography,
  alpha
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { FaCheck, FaChevronDown } from "react-icons/fa";

import { ICluster } from "@/@types/cluster";
import { getAllClusters } from "@/api/cluster";
import { useZone } from "@/context/ZoneContext";

const MAIN_ZONE = { name: "Main", type: "agent", url: "", id: "" } as ICluster;

export default function TopBarZone() {
  const { selectedZone, setSelectedZone } = useZone();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const open = Boolean(anchorEl);

  const { data, isLoading } = useQuery({
    queryKey: ["all-zone-list"],
    queryFn: () => getAllClusters()
  });

  const handleClick = (event: React.MouseEvent<HTMLElement>) => {
    setAnchorEl(event.currentTarget);
  };

  const handleClose = () => {
    setAnchorEl(null);
  };

  const handleZoneSelect = (zoneId: string) => {
    setSelectedZone(zoneId);
    window.location.replace("/alert-rule");
    handleClose();
  };

  const currentZone = useMemo(
    () => data?.find((zone) => zone.id === selectedZone) || MAIN_ZONE,
    [data, selectedZone]
  );

  const zoneList = useMemo(() => [MAIN_ZONE, ...(data ?? [])], [data]);

  return (
    <>
      <Stack
        direction="row"
        onClick={handleClick}
        sx={{
          justifyContent: "space-between",
          alignItems: "center",
          minWidth: 100,
          cursor: "pointer",
          paddingY: 1,
          paddingX: 1.3,
          borderRadius: 2,
          transition: "all 0.2s ease",
          backgroundColor: "action.hover",

          "&:hover": {
            backgroundColor: "action.selected"
          }
        }}
      >
        <Box
          sx={{
            display: "flex",
            flexDirection: "column"
          }}
        >
          <Typography
            variant="caption"
            sx={{
              color: "text.secondary",
              fontSize: 8
            }}
          >
            Zone
          </Typography>
          <Typography
            variant="body2"
            sx={{
              fontWeight: 600,
              fontSize: 12
            }}
          >
            {isLoading ? <Skeleton variant="text" width={60} height={24} /> : currentZone?.name}
          </Typography>
        </Box>
        <IconButton
          size="small"
          sx={{
            marginLeft: 0.5,
            padding: 0
          }}
        >
          <FaChevronDown size={14} />
        </IconButton>
      </Stack>
      <Menu
        anchorEl={anchorEl}
        open={open}
        onClose={handleClose}
        anchorOrigin={{
          vertical: "bottom",
          horizontal: "right"
        }}
        transformOrigin={{
          vertical: "top",
          horizontal: "right"
        }}
        slotProps={{
          paper: {
            sx: {
              mt: 1,
              minWidth: 180,
              borderRadius: 3,
              boxShadow: ({ palette }) => `0 1px 10px 0px ${alpha(palette.common.black, 0.1)}`
            }
          }
        }}
      >
        <Typography
          variant="caption"
          sx={{
            display: "block",
            color: "text.secondary",
            fontWeight: 600,
            paddingX: 1.1,
            pb: 0.7
          }}
        >
          Select Zone
        </Typography>
        {zoneList?.map((zone) => (
          <MenuItem
            key={zone.id}
            onClick={() => handleZoneSelect(zone.id)}
            selected={selectedZone === zone.id}
            sx={{
              paddingY: 1.4,
              paddingX: 2,
              "&.Mui-selected": {
                backgroundColor: ({ palette }) => alpha(palette.primary.main, 0.12)
              }
            }}
          >
            <ListItemText primary={zone.name} secondary={zone.type} />
            {selectedZone === zone.id && (
              <ListItemIcon sx={{ minWidth: "0 !important", marginLeft: 1.6 }}>
                <FaCheck color="primary" />
              </ListItemIcon>
            )}
          </MenuItem>
        ))}
      </Menu>
    </>
  );
}
