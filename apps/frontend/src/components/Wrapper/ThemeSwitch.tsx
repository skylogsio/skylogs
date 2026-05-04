"use client";

import { useState } from "react";

import { IconButton, ListItemIcon, ListItemText, Menu, MenuItem, Skeleton } from "@mui/material";
import { useColorScheme } from "@mui/material/styles";
import { IoMoon, IoSunny } from "react-icons/io5";
import { TbBrightnessFilled } from "react-icons/tb";

type ThemeMode = "system" | "light" | "dark";

const themeModes: { mode: ThemeMode; label: string; icon: React.ReactNode }[] = [
  { mode: "system", label: "System", icon: <TbBrightnessFilled fontSize="1.2rem" /> },
  { mode: "light", label: "Light", icon: <IoSunny fontSize="1.2rem" /> },
  { mode: "dark", label: "Dark", icon: <IoMoon fontSize="1.2rem" /> }
];

export default function ThemeSwitch() {
  const { mode, setMode } = useColorScheme();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const open = Boolean(anchorEl);

  const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
    setAnchorEl(event.currentTarget);
  };

  const handleClose = () => {
    setAnchorEl(null);
  };

  const handleModeSelect = (selectedMode: ThemeMode) => {
    setMode(selectedMode);
    handleClose();
  };

  if (!mode) {
    return <Skeleton variant="rounded" width={40} height={40} />;
  }

  const getCurrentIcon = () => {
    switch (mode) {
      case "dark":
        return <IoMoon />;
      case "light":
        return <IoSunny />;
      default:
        return <TbBrightnessFilled />;
    }
  };

  return (
    <>
      <IconButton
        onClick={handleClick}
        sx={{
          color: "text.secondary",
          backgroundColor: "action.hover",
          "&:hover": {
            backgroundColor: "action.selected"
          }
        }}
      >
        {getCurrentIcon()}
      </IconButton>
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
              minWidth: 150
            }
          }
        }}
      >
        {themeModes.map(({ mode: themeMode, label, icon }) => (
          <MenuItem
            key={themeMode}
            onClick={() => handleModeSelect(themeMode)}
            selected={mode === themeMode}
          >
            <ListItemIcon sx={{ color: ({ palette }) => palette.text.primary }}>
              {icon}
            </ListItemIcon>
            <ListItemText>{label}</ListItemText>
          </MenuItem>
        ))}
      </Menu>
    </>
  );
}
