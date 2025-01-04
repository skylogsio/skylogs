"use client";

import Image from "next/image";
import { Fragment, useState } from "react";

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
  Typography
} from "@mui/material";
import { signOut } from "next-auth/react";
import { FaAngleDown } from "react-icons/fa6";

import { useScopedI18n } from "@/locales/client";

import type { ProfileListType } from "./types";

const ListContents: Array<ProfileListType> = [
  { title: "list.manageAccount", iconSRC: "/static/icons/profile-manage-account.svg" },
  { title: "list.changePassword", iconSRC: "/static/icons/profile-change-password.svg" },
  { title: "list.activityLog", iconSRC: "/static/icons/profile-activity-log.svg" },
  { title: "list.logout", iconSRC: "/static/icons/profile-log-out.svg" }
];

export default function TopBarProfile() {
  const t = useScopedI18n("wrapper.profile");

  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);

  const handleOpen = (event: React.MouseEvent<HTMLElement>) => {
    setAnchorEl(anchorEl ? null : event.currentTarget);
  };

  const handleClose = () => setAnchorEl(null);

  const open = Boolean(anchorEl);
  const id = open ? "top-bar-profile-popover" : undefined;

  return (
    <>
      <Box
        display="flex"
        alignItems="center"
        onClick={handleOpen}
        marginRight="1rem"
        sx={{ cursor: "pointer" }}
      >
        <Image
          src="/static/images/default-profile.png"
          alt="profile"
          width={45}
          height={45}
          style={{ borderRadius: "10rem", width: "auto", height: "auto" }}
        />
        <Box display="flex" flexDirection="column" marginX="1rem">
          <Typography
            variant="body2"
            fontWeight="bold"
            sx={{
              whiteSpace: "nowrap",
              overflow: "hidden",
              maxWidth: "100px",
              textOverflow: "ellipsis"
            }}
          >
            {t("fullName")}
          </Typography>
          <Typography variant="caption">{t("role")}</Typography>
        </Box>
        <IconButton
          sx={{ border: ({ palette }) => `1px solid ${palette.grey[300]}`, padding: "0.2rem" }}
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
              borderRadius: "1rem",
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
          {ListContents.map((item, index) => {
            const size = item.title === "list.manageAccount" ? 18 : 15;
            return (
              <Fragment key={item.title}>
                <ListItem disablePadding>
                  <ListItemButton
                    sx={{ padding: "0.7rem 1rem" }}
                    {...(item.title === "list.logout" && { onClick: () => signOut() })}
                  >
                    <ListItemIcon sx={{ minWidth: 0, marginRight: "1rem" }}>
                      <Image
                        src={item.iconSRC}
                        alt={item.title}
                        width={size}
                        height={size}
                        style={{
                          width: size,
                          height: size
                        }}
                      />
                    </ListItemIcon>
                    <Typography variant="body2" whiteSpace="nowrap">
                      {t(item.title)}
                    </Typography>
                  </ListItemButton>
                </ListItem>
                {index !== ListContents.length - 1 && (
                  <Divider sx={{ borderColor: ({ palette }) => palette.grey[100] }} />
                )}
              </Fragment>
            );
          })}
        </List>
      </Popover>
    </>
  );
}
