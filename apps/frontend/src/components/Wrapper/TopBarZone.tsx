"use client";

import { useMemo, useState } from "react";

import {
  alpha,
  ListItemIcon,
  ListItemText,
  Menu,
  MenuItem,
  Skeleton,
  Typography,
  useTheme
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { IoGlobeOutline } from "react-icons/io5";

import { ICluster } from "@/@types/cluster";
import { getAllClusters } from "@/api/cluster";
import { useZone } from "@/context/ZoneContext";
import { useCurrentTheme } from "@/hooks";

import DashboardPillButton from "./DashboardPillButton";
import { getProfileMenuPaperSx } from "./topBarStyles";

const MAIN_ZONE = { name: "Main", type: "agent", url: "", id: "" } as ICluster;

export default function TopBarZone() {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();
  const { selectedZone, setSelectedZone } = useZone();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const open = Boolean(anchorEl);

  const { data, isLoading } = useQuery({
    queryKey: ["all-zone-list"],
    queryFn: () => getAllClusters()
  });

  const currentZone = useMemo(
    () => data?.find((zone) => zone.id === selectedZone) || MAIN_ZONE,
    [data, selectedZone]
  );

  const zoneList = useMemo(() => [MAIN_ZONE, ...(data ?? [])], [data]);

  return (
    <>
      <DashboardPillButton
        label="Zone"
        value={
          isLoading ? (
            <Skeleton variant="text" width={48} sx={{ display: "inline-block" }} />
          ) : (
            currentZone?.name
          )
        }
        onClick={(event) => setAnchorEl(event.currentTarget)}
        ariaLabel="Select zone"
        open={open}
        disabled={isLoading}
        minWidth={100}
        startIcon={<IoGlobeOutline size={16} />}
      />

      <Menu
        anchorEl={anchorEl}
        open={open}
        onClose={() => setAnchorEl(null)}
        anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
        transformOrigin={{ vertical: "top", horizontal: "right" }}
        slotProps={{ paper: { sx: { ...getProfileMenuPaperSx(theme, isDark), minWidth: 240 } } }}
      >
        <Typography
          variant="caption"
          sx={{
            display: "block",
            color: palette.text.secondary,
            fontWeight: 600,
            letterSpacing: "0.04em",
            textTransform: "uppercase",
            fontSize: "0.65rem",
            px: 1.75,
            pt: 0.75,
            pb: 0.5
          }}
        >
          Select Zone
        </Typography>
        {zoneList.map((zone) => {
          const selected = selectedZone === zone.id;
          return (
            <MenuItem
              key={zone.id || "main"}
              onClick={() => {
                setSelectedZone(zone.id);
                window.location.replace("/alert-rule");
                setAnchorEl(null);
              }}
              selected={selected}
              sx={{
                py: 1.25,
                px: 1.5,
                mx: 0.75,
                my: 0.25,
                borderRadius: 2,
                "&.Mui-selected": {
                  backgroundColor: alpha(palette.primary.main, 0.16),
                  "&:hover": { backgroundColor: alpha(palette.primary.main, 0.24) }
                }
              }}
            >
              <ListItemIcon
                sx={{
                  minWidth: 36,
                  color: selected ? palette.primary.main : palette.text.secondary
                }}
              >
                <IoGlobeOutline size={18} />
              </ListItemIcon>
              <ListItemText
                primary={zone.name}
                secondary={zone.type}
                slotProps={{
                  primary: {
                    sx: {
                      fontWeight: selected ? 700 : 500,
                      color: selected ? palette.primary.main : palette.text.primary
                    }
                  },
                  secondary: { sx: { fontSize: "0.75rem", textTransform: "capitalize" } }
                }}
              />
            </MenuItem>
          );
        })}
      </Menu>
    </>
  );
}
