import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";

import {
  alpha,
  Box,
  List,
  ListItem as MUIListItem,
  ListItemButton,
  Stack,
  Typography,
  useTheme
} from "@mui/material";
import {
  AiOutlineApi,
  AiOutlineUser,
  AiOutlineDatabase,
  AiOutlineCluster,
  AiOutlineAlert,
  AiOutlineCloud,
  AiOutlineSetting,
  AiOutlineFundProjectionScreen,
  AiOutlineTeam
} from "react-icons/ai";

import { useRole } from "@/hooks";
import { RoleType } from "@/utils/userUtils";

const SKYLOGS_VERSION = "0.15.0";

type URLType = {
  pathname: string;
  label: string;
  role?: RoleType | RoleType[];
  icon: React.ComponentType<{ size?: number | string; className?: string }>;
};

const URLS: Array<URLType> = [
  // { pathname: "/", label: "Home", icon: AiOutlineHome },
  { pathname: "/alert-rule", label: "Alert Rules", icon: AiOutlineAlert },
  { pathname: "/status", label: "Status", icon: AiOutlineFundProjectionScreen },
  { pathname: "/endpoints", label: "Endpoints", icon: AiOutlineApi },
  { pathname: "/users", label: "Users", role: ["owner", "manager"], icon: AiOutlineUser },
  { pathname: "/teams", label: "Teams", icon: AiOutlineTeam },
  {
    pathname: "/data-source",
    label: "Data Sources",
    role: ["owner", "manager"],
    icon: AiOutlineDatabase
  },
  { pathname: "/clusters", label: "Clusters", role: ["owner"], icon: AiOutlineCluster },
  { pathname: "/profile-services", label: "Profile Services", role: "owner", icon: AiOutlineCloud },
  { pathname: "/settings", label: "Settings", role: "owner", icon: AiOutlineSetting }
];

function ListItem(url: URLType) {
  const { palette } = useTheme();
  const pathname = usePathname();
  const { hasRole } = useRole();

  if (url.role) {
    if (!hasRole(url.role)) return;
  }

  const isActive =
    url.pathname === "/" ? pathname === url.pathname : pathname.includes(url.pathname);

  const IconComponent = url.icon;

  return (
    <MUIListItem
      key={url.pathname}
      sx={{
        position: "relative",
        paddingY: 0,
        paddingRight: 2,
        paddingLeft: isActive ? 0 : 2
      }}
    >
      <Stack direction="row" spacing={2} width="100%">
        {isActive && (
          <Box
            sx={{
              content: "''",
              display: "inline-block",
              height: "100%",
              width: 5,
              backgroundColor: `${palette.primary.main}!important`,
              position: "absolute",
              top: 0,
              left: 0,
              borderRadius: "0 0.6rem 0.6rem 0"
            }}
          ></Box>
        )}
        <ListItemButton
          component={Link}
          href={url.pathname}
          sx={{
            paddingY: 2,
            borderRadius: "0.6rem",
            backgroundColor: isActive
              ? `${alpha(palette.primary.main, 0.15)}!important`
              : "transparent",
            color: "inherit",
            display: "flex",
            alignItems: "center",
            gap: 1.5
          }}
        >
          <IconComponent size="1.4rem" />
          {url.label}
        </ListItemButton>
      </Stack>
    </MUIListItem>
  );
}

export default function SideBar() {
  const { palette } = useTheme();
  return (
    <Box height="100%" overflow="auto" sx={{ direction: "rtl" }}>
      <Stack width="100%" height="100%" sx={{ direction: "ltr" }}>
        <Box paddingX={7} marginY="-10%">
          <Image
            src="/static/images/logo.png"
            alt="Skylogs Logo"
            width="400"
            height="120"
            style={{ filter: `drop-shadow(0px 0px 16px ${palette.primary.light})` }}
          />
        </Box>
        <List>{URLS.map((url) => ListItem(url))}</List>
        <Stack alignItems="center" marginTop="auto">
          <Typography variant="caption" color="text.secondary" fontSize={10}>
            version {SKYLOGS_VERSION}
          </Typography>
        </Stack>
      </Stack>
    </Box>
  );
}
