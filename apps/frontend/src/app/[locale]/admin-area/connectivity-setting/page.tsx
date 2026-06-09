"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import { Box, Typography, Grid, Card, alpha } from "@mui/material";
import { grey } from "@mui/material/colors";
import { motion } from "framer-motion";
import { IoIosArrowForward } from "react-icons/io";

import { ENDPOINT_COLORS } from "@/provider/MuiProvider";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

const connectivityCards = [
  {
    href: "/telegram-proxies",
    icon: ENDPOINT_CONFIG["telegram"].icon,
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
    <Box
      sx={{
        minHeight: "100%"
      }}
    >
      <Box
        component={motion.div}
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4, ease: "easeOut" }}
        sx={{
          textAlign: "center",
          my: 3
        }}
      >
        <Typography
          variant="h3"
          component="h1"
          sx={{
            fontWeight: 800,

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
          sx={{
            fontWeight: 400,
            maxWidth: 600,
            mx: "auto"
          }}
        >
          Manage Telegram proxy configurations and also Manage Call, Email and SMS providers
        </Typography>
      </Box>
      <Box
        sx={{
          width: "90%",
          minHeight: "100%",
          display: "flex",
          justifyContent: "center",
          alignItems: "center",
          paddingX: 1,
          marginX: "auto"
        }}
      >
        <Grid
          container
          spacing={3}
          sx={{
            marginTop: 3
          }}
        >
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
                  display: "flex",
                  justifyContent: "center",
                  alignItems: "center",
                  boxSizing: "border-box !important",
                  borderRadius: 3,
                  cursor: "pointer",
                  boxShadow: `0 3px 5px ${alpha(grey[400], 0.15)}`,
                  border: 2,
                  borderColor: item.color,
                  height: 1,
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
                  sx={{
                    padding: 3,
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    textDecoration: "none",
                    gap: 2
                  }}
                >
                  <Box
                    sx={{
                      display: "flex",
                      alignItems: "center",
                      gap: 2,
                      flex: 1
                    }}
                  >
                    <Box
                      className="connectivity-setting-icon"
                      sx={{
                        display: "flex",
                        justifyContent: "center",
                        alignItems: "center",
                        width: 70,
                        height: 70,
                        borderRadius: "50%",
                        backgroundColor: item.color,
                        color: ({ palette }) => palette.common.white,
                        flexShrink: 0,
                        transition: "all 0.2s ease"
                      }}
                    >
                      <item.icon size={item.iconSize} />
                    </Box>
                    <Box
                      sx={{
                        flex: 1
                      }}
                    >
                      <Typography
                        variant="h6"
                        sx={{
                          color: "text.primary",
                          fontWeight: "bold",
                          marginBottom: 0.5
                        }}
                      >
                        {item.title}
                      </Typography>
                      <Typography
                        variant="body2"
                        sx={{
                          color: "text.secondary",
                          opacity: 0.8
                        }}
                      >
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
