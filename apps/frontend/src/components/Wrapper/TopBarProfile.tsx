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
  List,
  ListItem,
  ListItemButton,
  ListItemIcon,
  Popover,
  Skeleton,
  Stack,
  Typography
} from "@mui/material";
import { signOut } from "next-auth/react";
import { FaAngleDown } from "react-icons/fa6";

import { useRole } from "@/hooks";
import { useScopedI18n } from "@/locales/client";

export default function TopBarProfile() {
  const pathname = usePathname();
  const t = useScopedI18n("wrapper.profile");
  const { userInfo, hasRole } = useRole();

  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);

  const handleOpen = (event: React.MouseEvent<HTMLElement>) => {
    setAnchorEl(anchorEl ? null : event.currentTarget);
  };

  const handleClose = () => setAnchorEl(null);

  const open = Boolean(anchorEl);
  const id = open ? "top-bar-profile-popover" : undefined;

  const isAdmin = hasRole("owner");

  const isAdminArea = pathname.includes("admin-area");
  const adminButtonHREF = isAdminArea ? "/alert-rule" : "admin-area";

  return (
    <>
      <Box
        onClick={handleOpen}
        sx={{
          display: "flex",
          alignItems: "center",
          marginRight: 2,
          cursor: "pointer"
        }}
      >
        <Image
          src="/static/images/default-profile.png"
          alt="profile"
          width={45}
          height={45}
          style={{ borderRadius: 10, width: "auto", height: "auto" }}
        />
        <Stack
          sx={{
            marginX: 2
          }}
        >
          {userInfo ? (
            <>
              <Typography
                variant="body2"
                sx={{
                  fontWeight: "bold",
                  whiteSpace: "nowrap",
                  overflow: "hidden",
                  maxWidth: 100,
                  textOverflow: "ellipsis"
                }}
              >
                {userInfo?.name}
              </Typography>
              <Typography
                variant="caption"
                sx={{
                  textTransform: "capitalize"
                }}
              >
                {userInfo?.roles[0]}
              </Typography>
            </>
          ) : (
            <>
              <Skeleton variant="text" width={60} />
              <Skeleton variant="text" width={40} />
            </>
          )}
        </Stack>
        <IconButton
          sx={{ border: 1, borderColor: ({ palette }) => palette.grey[300], padding: 0.4 }}
        >
          <FaAngleDown size="0.7rem" />
        </IconButton>
      </Box>
      <Popover
        id={id}
        open={open}
        elevation={5}
        anchorEl={anchorEl}
        slotProps={{
          paper: {
            sx: {
              borderRadius: 4,
              boxShadow: ({ palette }) => `0 1px 10px 0px ${alpha(palette.common.black, 0.1)}`
            }
          }
        }}
        onClose={handleClose}
        anchorOrigin={{
          vertical: "bottom",
          horizontal: "right"
        }}
        transformOrigin={{
          vertical: "top",
          horizontal: "right"
        }}
      >
        <List disablePadding>
          {isAdmin && (
            <>
              <ListItem disablePadding>
                <ListItemButton
                  component={Link}
                  href={adminButtonHREF}
                  onClick={() => handleClose()}
                  sx={{ paddingY: 1.4, paddingX: 2 }}
                >
                  <ListItemIcon sx={{ minWidth: 0, marginRight: 2 }}>
                    <Image
                      src={
                        isAdminArea
                          ? "/static/icons/profile-alert-area.svg"
                          : "/static/icons/profile-admin-area.svg"
                      }
                      alt="admin-area"
                      width={18}
                      height={18}
                      style={{
                        width: 18,
                        height: 18
                      }}
                    />
                  </ListItemIcon>
                  <Typography
                    variant="body2"
                    sx={{
                      whiteSpace: "nowrap"
                    }}
                  >
                    {isAdminArea ? "Alert Area" : "Admin Area"}
                  </Typography>
                </ListItemButton>
              </ListItem>
              <Divider sx={{ borderColor: ({ palette }) => palette.grey[100] }} />
            </>
          )}
          <ListItem disablePadding>
            <ListItemButton sx={{ paddingY: 1.4, paddingX: 2 }} onClick={() => signOut()}>
              <ListItemIcon sx={{ minWidth: 0, marginRight: 2 }}>
                <Image
                  src="/static/icons/profile-log-out.svg"
                  alt="log-out"
                  width={18}
                  height={18}
                  style={{
                    width: 18,
                    height: 18
                  }}
                />
              </ListItemIcon>
              <Typography
                variant="body2"
                sx={{
                  whiteSpace: "nowrap"
                }}
              >
                {t("list.logout")}
              </Typography>
            </ListItemButton>
          </ListItem>
        </List>
      </Popover>
    </>
  );
}
