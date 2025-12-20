"use client";

import { useRouter } from "next/navigation";

import { Box, Typography, Grid2 as Grid, Card, Button, alpha } from "@mui/material";
import { grey } from "@mui/material/colors";
import { AiFillSetting } from "react-icons/ai";
import { HiUser, HiUsers } from "react-icons/hi";
import { IoIosArrowForward } from "react-icons/io";
import { PiPlugsConnectedFill } from "react-icons/pi";

const overviewCards = [
  {
    title: "Users",
    pathname: "",
    description: "Manage user accounts, roles, and access permissions within the system",
    icon: HiUser,
    color: "#FF8D28",
    gradient: "linear-gradient(135deg, #FF8D28 0%, #FF8D2888 100%)"
  },
  {
    title: "Team",
    pathname: "",
    description: "Manage user accounts, roles, and access permissions within the system",
    icon: HiUsers,
    color: "#6155F5",
    gradient: "linear-gradient(135deg, #6155F5 0%, #6155F588 100%)"
  },
  {
    title: "Core Setting",
    pathname: "/core-setting",
    description: "Configure cluster settings and agent connections for distributed operations",
    icon: AiFillSetting,
    color: "#13C82B",
    gradient: "linear-gradient(135deg, #13C82B 0%, #13C82B88 100%)"
  },
  {
    title: "Connectivity Setting",
    pathname: "/connectivity-setting",
    description: "Manage proxy configurations for Telegram connectivity and routing",
    icon: PiPlugsConnectedFill,
    color: "#0088FF",
    gradient: "linear-gradient(135deg, #0088FF 0%, #0088FF88 100%)"
  }
];

export default function OverviewPage() {
  const router = useRouter();
  return (
    <Box minHeight="100%">
      <Box textAlign="center" my={3}>
        <Typography
          variant="h3"
          fontWeight="800"
          component="h1"
          sx={{
            background: ({ palette }) =>
              `linear-gradient(135deg, ${palette.primary.main}, ${palette.secondary.main})`,
            backgroundClip: "text",
            WebkitBackgroundClip: "text",
            WebkitTextFillColor: "transparent",
            mb: 1
          }}
        >
          Overview
        </Typography>
        <Typography
          variant="h6"
          color="textSecondary"
          fontWeight="400"
          sx={{ maxWidth: 600, mx: "auto" }}
        >
          Configure and manage your application settings across different categories
        </Typography>
      </Box>
      <Box
        width="90%"
        minHeight="100%"
        display="flex"
        justifyContent="center"
        alignItems="center"
        paddingX={1}
        marginX="auto"
      >
        <Grid container spacing={3} marginTop={3}>
          {overviewCards.map((item) => (
            <Grid
              size={6}
              key={item.title}
              component={Card}
              onClick={() => router.push(`admin-area${item.pathname}`)}
              sx={{
                borderRadius: 3,
                cursor: "pointer",
                boxShadow: `0 3px 5px ${alpha(grey[400], 0.15)}`,
                borderLeft: `6px solid ${item.color}`,
                transition: "all 0.2s ease-out",
                "&:hover": {
                  transform: "scale(1.02)",
                  boxShadow: `0 12px 24px ${alpha(grey[400], 0.2)}`,
                  borderLeftWidth: "8px",
                  "& .overview-card-icon": {
                    transform: "scale(1.1)",
                    top: "-6px",
                    right: "-6px"
                  }
                }
              }}
            >
              <Box padding={2} position="relative">
                <Box
                  display="flex"
                  justifyContent="center"
                  alignItems="center"
                  width={80}
                  height={80}
                  borderRadius="50%"
                  className="overview-card-icon"
                  sx={{
                    background: item.gradient,
                    color: ({ palette }) => palette.common.white,
                    position: "absolute",
                    top: "-10px",
                    right: "-10px",
                    transition: "all 0.2s ease"
                  }}
                >
                  <item.icon size="50%" />
                </Box>
                <Button
                  size="small"
                  endIcon={<IoIosArrowForward size="0.9rem" />}
                  sx={{ color: grey[500], textTransform: "none" }}
                >
                  View More
                </Button>
                <Typography
                  variant="h6"
                  letterSpacing={1}
                  marginTop={4}
                  marginBottom={2}
                  fontWeight="bold"
                >
                  {item.title}
                </Typography>
                <Typography
                  variant="body1"
                  color="text.secondary"
                  sx={{ opacity: 0.7, width: "95%", marginX: "auto" }}
                >
                  {item.description}
                </Typography>
              </Box>
            </Grid>
          ))}
        </Grid>
      </Box>
    </Box>
  );
}
