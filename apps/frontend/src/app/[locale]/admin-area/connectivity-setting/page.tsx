"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import { Box, Typography, Grid2 as Grid, Card, alpha } from "@mui/material";
import { grey } from "@mui/material/colors";
import { motion } from "framer-motion";
import { IoIosArrowForward } from "react-icons/io";
import { RiTelegram2Fill } from "react-icons/ri";

import { ENDPOINT_COLORS } from "@/provider/MuiProvider";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

const connectivityCards = [
  {
    href: "/telegram-proxies",
    icon: RiTelegram2Fill,
    iconSize: "2.9rem",
    color: ENDPOINT_COLORS["telegram"],
    title: "Telegram Proxies",
    description: "Used to configure proxy settings for connecting to Telegram."
  },
  {
    href: "/sms",
    icon: ENDPOINT_CONFIG["sms"].icon,
    iconSize: "2.3rem",
    color: ENDPOINT_COLORS["sms"],
    title: "SMS Config",
    description: "Used to set up SMS service provider and messaging details."
  },
  {
    href: "/call",
    icon: ENDPOINT_CONFIG["call"].icon,
    iconSize: "2.3rem",
    color: ENDPOINT_COLORS["call"],
    title: "Call Config",
    description: "Used to configure voice call service settings."
  },
  {
    href: "/email",
    icon: ENDPOINT_CONFIG["email"].icon,
    iconSize: "2.3rem",
    color: ENDPOINT_COLORS["email"],
    title: "Email Config",
    description: "Used to configure email settings such as username, password, host, and port."
  }
];

export default function ConnectivitySetting() {
  const pathname = usePathname();
  return (
    <Box minHeight="100%">
      <Box
        component={motion.div}
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4, ease: "easeOut" }}
        textAlign="center"
        my={3}
      >
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
          Connectivity Setting
        </Typography>
        <Typography
          variant="h6"
          color="textSecondary"
          fontWeight="400"
          sx={{ maxWidth: 600, mx: "auto" }}
        >
          Manage Telegram proxy configurations and also Manage Call, Email and SMS providers
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
          {connectivityCards.map((item, index) => (
            <Grid
              size={6}
              key={item.title}
              component={motion.div}
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{
                duration: 0.5,
                ease: "easeOut",
                delay: 0.2 + index * 0.1
              }}
            >
              <Card
                sx={{
                  boxSizing: "border-box !important",
                  borderRadius: 3,
                  cursor: "pointer",
                  boxShadow: `0 3px 5px ${alpha(grey[400], 0.15)}`,
                  border: `2px solid ${item.color}`,
                  height: "100%",
                  transition: "all 0.2s ease-out",
                  "&:hover": {
                    transform: "translateY(-4px) scale(1.01)",
                    boxShadow: `0 12px 24px ${alpha(item.color, 0.25)}`,
                    "& .connectivity-setting-icon": {
                      transform: "scale(1.05)"
                    }
                  }
                }}
              >
                <Box
                  component={Link}
                  href={pathname + item.href}
                  padding={3}
                  display="flex"
                  alignItems="center"
                  justifyContent="space-between"
                  gap={2}
                >
                  <Box display="flex" alignItems="center" gap={2} flex={1}>
                    <Box
                      display="flex"
                      justifyContent="center"
                      alignItems="center"
                      width={70}
                      height={70}
                      borderRadius="50%"
                      className="connectivity-setting-icon"
                      sx={{
                        backgroundColor: item.color,
                        color: ({ palette }) => palette.common.white,
                        flexShrink: 0,
                        transition: "all 0.2s ease"
                      }}
                    >
                      <item.icon size={item.iconSize} />
                    </Box>
                    <Box flex={1}>
                      <Typography variant="h6" fontWeight="bold" marginBottom={0.5}>
                        {item.title}
                      </Typography>
                      <Typography variant="body2" color="text.secondary" sx={{ opacity: 0.8 }}>
                        {item.description}
                      </Typography>
                    </Box>
                  </Box>
                  <IoIosArrowForward
                    size={24}
                    style={{
                      color: grey[600],
                      flexShrink: 0
                    }}
                  />
                </Box>
              </Card>
            </Grid>
          ))}
        </Grid>
      </Box>
    </Box>
  );
}
