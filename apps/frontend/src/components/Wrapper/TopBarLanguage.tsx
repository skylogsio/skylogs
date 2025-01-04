"use client";

import Image from "next/image";
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
  Typography
} from "@mui/material";
import { FaAngleDown, FaCheck } from "react-icons/fa6";

import { useCurrentLocale, useScopedI18n, useChangeLocale } from "@/locales/client";

import { LocalesListType } from "./types";

const LanguageList: Array<LocalesListType> = [
  { locale: "fa", title: "فارسی", iconSRC: "/static/icons/iran.svg" },
  { locale: "en", title: "English", iconSRC: "/static/icons/united-kingdom.svg" }
];

export default function TopBarLanguage() {
  const t = useScopedI18n("wrapper");
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const changeLocale = useChangeLocale();
  const currentLocale = useCurrentLocale();
  const currentLanguage = LanguageList.find((item) => item.locale === currentLocale);

  const handleOpen = (event: React.MouseEvent<HTMLElement>) => {
    setAnchorEl(anchorEl ? null : event.currentTarget);
  };

  const handleClose = () => setAnchorEl(null);

  const open = Boolean(anchorEl);
  const id = open ? "top-bar-profile-popover" : undefined;

  function handleChangeLanguage(item: LocalesListType): void {
    if (item.locale !== currentLocale) {
      changeLocale(item.locale);
    }
  }

  return (
    <>
      <Box
        display="flex"
        alignItems="center"
        onClick={handleOpen}
        marginX="1rem"
        sx={{ cursor: "pointer" }}
      >
        {currentLanguage && (
          <Image
            src={currentLanguage?.iconSRC}
            alt="profile"
            width={45}
            height={30}
            style={{ borderRadius: "0.4rem" }}
          />
        )}
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
            {currentLanguage?.title}
          </Typography>
        </Box>
        <IconButton sx={{ padding: "0.2rem" }}>
          <FaAngleDown size="0.7rem" />
        </IconButton>
      </Box>
      <Popover
        id={id}
        open={open}
        elevation={5}
        anchorEl={anchorEl}
        marginThreshold={60}
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
          horizontal: "left"
        }}
        transformOrigin={{
          vertical: "top",
          horizontal: "left"
        }}
      >
        <Typography padding="0.7rem 1rem">{t("language")}</Typography>
        <Divider />
        <List disablePadding>
          {LanguageList.map((item) => (
            <ListItem key={item.locale} disablePadding onClick={() => handleChangeLanguage(item)}>
              <ListItemButton selected={currentLocale === item.locale} sx={{ padding: "1rem" }}>
                <ListItemIcon sx={{ minWidth: 0, marginRight: "1rem" }}>
                  <Image
                    src={item.iconSRC}
                    alt={item.title}
                    width={45}
                    height={30}
                    style={{ borderRadius: "0.4rem" }}
                  />
                </ListItemIcon>
                <Typography variant="body1" whiteSpace="nowrap" marginRight="2rem">
                  {item.title}
                </Typography>
                {currentLocale === item.locale && (
                  <ListItemIcon sx={{ minWidth: 0, marginLeft: "auto" }}>
                    <FaCheck />
                  </ListItemIcon>
                )}
              </ListItemButton>
            </ListItem>
          ))}
        </List>
      </Popover>
    </>
  );
}
