import Link from "next/link";
import { usePathname } from "next/navigation";

import { Box, List, ListItem as MUIListItem, ListItemButton, Stack } from "@mui/material";
import {
  AiOutlineApi,
  AiOutlineUser,
  AiOutlineDatabase,
  AiOutlineCluster,
  AiOutlineAlert,
  AiOutlineCloud,
  AiOutlineSetting,
  AiOutlineFundProjectionScreen
} from "react-icons/ai";

import { useRole } from "@/hooks";
import { RoleType } from "@/utils/userUtils";

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
              backgroundColor: ({ palette }) => `${palette.primary.main}!important`,
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
            backgroundColor: ({ palette }) =>
              isActive ? `${palette.primary.main}!important` : "transparent",
            color: ({ palette }) => (isActive ? palette.common.white : "inherit"),
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
  return (
    <Stack>
      <List>{URLS.map((url) => ListItem(url))}</List>
    </Stack>
  );
}
