"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";

import {
  alpha,
  Box,
  Divider,
  IconButton,
  Menu,
  Skeleton,
  Typography,
  useTheme
} from "@mui/material";
import { signOut } from "next-auth/react";
import { IoMoon, IoPersonCircle, IoSunny } from "react-icons/io5";
import { TbBrightnessFilled } from "react-icons/tb";

import { useCurrentTheme, useRole, type ThemePreference } from "@/hooks";
import { useScopedI18n } from "@/locales/client";

import DashboardPillButton from "./DashboardPillButton";
import { getProfileMenuPaperSx } from "./topBarStyles";

const themeOptions: {
  mode: ThemePreference;
  label: string;
  icon: React.ReactNode;
}[] = [
  { mode: "light", label: "Light", icon: <IoSunny size={16} /> },
  { mode: "dark", label: "Dark", icon: <IoMoon size={16} /> },
  { mode: "system", label: "System", icon: <TbBrightnessFilled size={16} /> }
];

const profileMenuRowSx = {
  display: "flex",
  alignItems: "center",
  justifyContent: "space-between",
  px: 1.75,
  py: 0.75,
  width: 1,
  boxSizing: "border-box" as const,
  textDecoration: "none",
  border: "none",
  background: "transparent",
  cursor: "pointer",
  textAlign: "left" as const,
  transition: "background-color 180ms ease"
};

type ProfileMenuRowProps = {
  label: string;
  icon: React.ReactNode;
  onClick?: () => void;
  href?: string;
  labelColor?: string;
  hoverColor?: string;
};

function ProfileMenuRow({ label, icon, onClick, href, labelColor, hoverColor }: ProfileMenuRowProps) {
  const theme = useTheme();
  const { palette } = theme;

  const rowSx = {
    ...profileMenuRowSx,
    color: "inherit",
    "&:hover": {
      backgroundColor: alpha(hoverColor ?? palette.primary.main, 0.06)
    }
  };

  const content = (
    <>
      <Typography
        variant="body2"
        sx={{ color: labelColor ?? palette.text.secondary, fontSize: "0.8125rem" }}
      >
        {label}
      </Typography>
      <Box
        sx={{
          display: "inline-flex",
          alignItems: "center",
          justifyContent: "center",
          borderRadius: 1,
          p: 0.5,
          lineHeight: 0,
          color: labelColor ?? palette.text.secondary,
          flexShrink: 0
        }}
      >
        {icon}
      </Box>
    </>
  );

  if (href) {
    return (
      <Box component={Link} href={href} onClick={onClick} sx={rowSx}>
        {content}
      </Box>
    );
  }

  return (
    <Box component="button" type="button" onClick={onClick} sx={rowSx}>
      {content}
    </Box>
  );
}

export default function TopBarProfile() {
  const pathname = usePathname();
  const theme = useTheme();
  const { palette } = theme;
  const { isDark, preference, isReady, setMode } = useCurrentTheme();
  const t = useScopedI18n("wrapper.profile");
  const { userInfo, hasRole } = useRole();

  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const open = Boolean(anchorEl);

  const isAdmin = hasRole("owner");
  const isAdminArea = pathname.includes("admin-area");
  const adminButtonHREF = isAdminArea ? "/alert-rule" : "/admin-area";

  const handleClose = () => setAnchorEl(null);

  return (
    <>
      <DashboardPillButton
        label={
          userInfo ? (
            userInfo.name
          ) : (
            <Skeleton variant="text" width={72} sx={{ display: "inline-block" }} />
          )
        }
        value={
          userInfo ? (
            userInfo.roles[0]
          ) : (
            <Skeleton variant="text" width={48} sx={{ display: "inline-block" }} />
          )
        }
        profileLayout
        onClick={(event) => setAnchorEl(event.currentTarget)}
        ariaLabel="Open profile menu"
        open={open}
        minWidth={128}
        startIcon={<IoPersonCircle size={20} />}
      />

      <Menu
        anchorEl={anchorEl}
        open={open}
        onClose={handleClose}
        anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
        transformOrigin={{ vertical: "top", horizontal: "right" }}
        slotProps={{ paper: { sx: { ...getProfileMenuPaperSx(theme, isDark), minWidth: 240 } } }}
      >
        {userInfo && (
          <Box sx={{ px: 1.75, py: 1.25 }}>
            <Typography variant="body2" sx={{ fontWeight: 700 }}>
              {userInfo.name}
            </Typography>
            <Typography
              variant="caption"
              sx={{ color: palette.text.secondary, textTransform: "capitalize" }}
            >
              {userInfo.roles[0]}
            </Typography>
          </Box>
        )}

        {userInfo && <Divider sx={{ borderColor: alpha(palette.primary.main, 0.12), mx: 1 }} />}

        <Box sx={{ ...profileMenuRowSx, cursor: "default", "&:hover": { backgroundColor: "transparent" } }}>
          <Typography variant="body2" sx={{ color: palette.text.secondary, fontSize: "0.8125rem" }}>
            Theme
          </Typography>
          {isReady ? (
            <Box sx={{ display: "flex", gap: 0.25 }}>
              {themeOptions.map(({ mode, label, icon }) => {
                const selected = preference === mode;
                return (
                  <IconButton
                    key={mode}
                    size="small"
                    aria-label={label}
                    aria-pressed={selected}
                    onClick={() => setMode(mode)}
                    sx={{
                      borderRadius: 1,
                      display: "flex",
                      flexDirection: "row",
                      alignItems: "center",
                      gap: selected ? 0.5 : 0,
                      px: selected ? 1 : 0.5,
                      color: selected ? palette.primary.main : palette.text.secondary,
                      backgroundColor: selected ? alpha(palette.primary.main, 0.12) : "transparent",
                      "&:hover": {
                        backgroundColor: alpha(palette.primary.main, selected ? 0.18 : 0.08)
                      }
                    }}
                  >
                    {selected && (
                      <Typography
                        component="span"
                        variant="caption"
                        sx={{
                          fontWeight: 600,
                          fontSize: "0.75rem",
                          lineHeight: 1,
                          color: "inherit"
                        }}
                      >
                        {label}
                      </Typography>
                    )}
                    {icon}
                  </IconButton>
                );
              })}
            </Box>
          ) : (
            <Skeleton variant="rounded" width={96} height={28} sx={{ borderRadius: 1 }} />
          )}
        </Box>

        <Divider sx={{ borderColor: alpha(palette.primary.main, 0.12), mx: 1 }} />

        {isAdmin && (
          <ProfileMenuRow
            label={isAdminArea ? "Alert Area" : "Admin Area"}
            href={adminButtonHREF}
            onClick={handleClose}
            icon={
              <Image
                src={
                  isAdminArea
                    ? "/static/icons/profile-alert-area.svg"
                    : "/static/icons/profile-admin-area.svg"
                }
                alt=""
                width={16}
                height={16}
              />
            }
          />
        )}

        <ProfileMenuRow
          label={t("list.logout")}
          onClick={() => signOut()}
          labelColor={palette.error.main}
          hoverColor={palette.error.main}
          icon={
            <Image src="/static/icons/profile-log-out.svg" alt="" width={16} height={16} />
          }
        />
      </Menu>
    </>
  );
}
