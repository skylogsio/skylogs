"use client";
import { useRouter } from "next/navigation";

import {
  Box,
  Card,
  CardContent,
  Container,
  Grid2 as Grid,
  Stack,
  Typography,
  useTheme,
  alpha,
  Chip
} from "@mui/material";
import { HiServer, HiCube, HiChevronRight } from "react-icons/hi";

interface SettingOption {
  title: string;
  description: string;
  icon: React.ReactNode;
  path: string;
  color: string;
  category: "Core" | "Connectivity" | "Security" | "System";
  isAvailable: boolean;
  comingSoon?: boolean;
}

export default function SettingsPage() {
  const router = useRouter();
  const { palette } = useTheme();

  const settingsOptions: SettingOption[] = [
    // Core Settings
    {
      title: "Cluster Configuration",
      description: "Configure cluster settings and agent connections for distributed operations",
      icon: <HiCube size="2.2rem" />,
      path: "/settings/cluster-config",
      color: "#4caf50",
      category: "Core",
      isAvailable: true
    },
    {
      title: "Telegram Proxies",
      description: "Manage proxy configurations for Telegram connectivity and routing",
      icon: <HiServer size="2.2rem" />,
      path: "/settings/telegram",
      color: "#2196f3",
      category: "Connectivity",
      isAvailable: true
    }
    // {
    //   title: "User Management",
    //   description: "Configure user accounts, roles, and access permissions",
    //   icon: <HiUser size="2.2rem" />,
    //   path: "/settings/users",
    //   color: "#ff9800",
    //   category: "Security",
    //   isAvailable: false,
    //   comingSoon: true
    // },
    // {
    //   title: "Authentication",
    //   description: "Set up authentication providers and security policies",
    //   icon: <HiShieldCheck size="2.2rem" />,
    //   path: "/settings/auth",
    //   color: "#e91e63",
    //   category: "Security",
    //   isAvailable: false,
    //   comingSoon: true
    // },
    // {
    //   title: "Database Settings",
    //   description: "Configure database connections and backup settings",
    //   icon: <HiDatabase size="2.2rem" />,
    //   path: "/settings/database",
    //   color: "#9c27b0",
    //   category: "System",
    //   isAvailable: false,
    //   comingSoon: true
    // },
    // {
    //   title: "Network Configuration",
    //   description: "Manage network settings, DNS, and connectivity options",
    //   icon: <HiGlobe size="2.2rem" />,
    //   path: "/settings/network",
    //   color: "#00bcd4",
    //   category: "Connectivity",
    //   isAvailable: false,
    //   comingSoon: true
    // },
    // {
    //   title: "Notifications",
    //   description: "Configure alert systems and notification preferences",
    //   icon: <HiBell size="2.2rem" />,
    //   path: "/settings/notifications",
    //   color: "#ff5722",
    //   category: "System",
    //   isAvailable: false,
    //   comingSoon: true
    // },
    // {
    //   title: "System Preferences",
    //   description: "General system settings and application preferences",
    //   icon: <HiCog size="2.2rem" />,
    //   path: "/settings/system",
    //   color: "#607d8b",
    //   category: "System",
    //   isAvailable: false,
    //   comingSoon: true
    // }
  ];

  const categories = [
    "Core",
    "Connectivity"
    //  "Security", "System"
  ] as const;
  const categoryColors = {
    Core: "#4caf50",
    Connectivity: "#2196f3"
    // Security: "#e91e63",
    // System: "#ff9800"
  };

  const handleOptionClick = (option: SettingOption) => {
    if (option.isAvailable) {
      router.push(option.path);
    }
  };

  return (
    <Container maxWidth="lg" sx={{ py: 4 }}>
      <Stack spacing={4}>
        <Box textAlign="center" mb={2}>
          <Typography
            variant="h3"
            fontWeight="800"
            component="h1"
            sx={{
              background: `linear-gradient(135deg, ${palette.primary.main}, ${palette.secondary.main})`,
              backgroundClip: "text",
              WebkitBackgroundClip: "text",
              WebkitTextFillColor: "transparent",
              mb: 1
            }}
          >
            Settings
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
        <Stack direction="row" spacing={4}>
          {categories.map((category) => {
            const categoryOptions = settingsOptions.filter(
              (option) => option.category === category
            );

            return (
              <Box key={category} id="lksdjflksdjflksjdf">
                <Stack direction="row" alignItems="center" spacing={2} mb={3}>
                  <Box
                    sx={{
                      width: 4,
                      height: 32,
                      backgroundColor: categoryColors[category],
                      borderRadius: 2
                    }}
                  />
                  <Typography variant="h5" fontWeight="700" color={categoryColors[category]}>
                    {category} Settings
                  </Typography>
                  <Chip
                    label={`${categoryOptions.length} items`}
                    size="small"
                    sx={{
                      backgroundColor: alpha(categoryColors[category], 0.1),
                      color: categoryColors[category],
                      fontWeight: 600
                    }}
                  />
                </Stack>

                <Grid container spacing={3}>
                  {categoryOptions.map((option) => (
                    <Grid key={option.title} size={12}>
                      <Card
                        onClick={() => handleOptionClick(option)}
                        sx={{
                          cursor: option.isAvailable ? "pointer" : "not-allowed",
                          height: "100%",
                          boxShadow: "none",
                          position: "relative",
                          overflow: "visible",
                          transition: "all 0.3s cubic-bezier(0.4, 0, 0.2, 1)",
                          opacity: option.isAvailable ? 1 : 0.6,
                          "&:hover": option.isAvailable
                            ? {
                                transform: "translateY(-8px)",
                                boxShadow: `0 12px 24px ${alpha(option.color, 0.15)}`,
                                "& .option-icon": {
                                  transform: "scale(1.1)",
                                  color: option.color
                                },
                                "& .chevron-icon": {
                                  opacity: 1,
                                  transform: "translateX(4px)"
                                }
                              }
                            : {},
                          border: `1px solid ${alpha(option.color, 0.1)}`,
                          "&::before": {
                            content: "''",
                            position: "absolute",
                            top: 0,
                            left: 0,
                            right: 0,
                            height: 3,
                            background: `linear-gradient(90deg, ${option.color}, ${alpha(option.color, 0.6)})`,
                            borderRadius: "4px 4px 0 0"
                          }
                        }}
                      >
                        <CardContent sx={{ p: 3, height: "100%" }}>
                          <Stack height="100%" justifyContent="space-between">
                            <Stack spacing={2}>
                              {/* Icon and Status */}
                              <Stack
                                direction="row"
                                justifyContent="space-between"
                                alignItems="flex-start"
                              >
                                <Box
                                  className="option-icon"
                                  sx={{
                                    p: 2,
                                    borderRadius: "12px",
                                    backgroundColor: alpha(option.color, 0.1),
                                    color: alpha(option.color, 0.8),
                                    transition: "all 0.2s ease",
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "center"
                                  }}
                                >
                                  {option.icon}
                                </Box>
                                {option.comingSoon && (
                                  <Chip
                                    label="Coming Soon"
                                    size="small"
                                    sx={{
                                      backgroundColor: alpha(palette.warning.main, 0.1),
                                      color: palette.warning.main,
                                      fontWeight: 600,
                                      fontSize: "0.7rem"
                                    }}
                                  />
                                )}
                              </Stack>

                              <Stack spacing={1}>
                                <Stack
                                  direction="row"
                                  alignItems="center"
                                  justifyContent="space-between"
                                >
                                  <Typography variant="h6" fontWeight="700" color="text.primary">
                                    {option.title}
                                  </Typography>
                                  {option.isAvailable && (
                                    <HiChevronRight
                                      className="chevron-icon"
                                      size="1.2rem"
                                      style={{
                                        opacity: 0,
                                        transition: "all 0.2s ease",
                                        color: option.color
                                      }}
                                    />
                                  )}
                                </Stack>
                                <Typography
                                  variant="body2"
                                  color="text.secondary"
                                  sx={{
                                    lineHeight: 1.6,
                                    fontSize: "0.875rem"
                                  }}
                                >
                                  {option.description}
                                </Typography>
                              </Stack>
                            </Stack>

                            <Box mt={2}>
                              <Box
                                sx={{
                                  display: "inline-flex",
                                  alignItems: "center",
                                  px: 1.5,
                                  py: 0.5,
                                  borderRadius: "16px",
                                  backgroundColor: option.isAvailable
                                    ? alpha(palette.success.main, 0.1)
                                    : alpha(palette.grey[500], 0.1),
                                  color: option.isAvailable
                                    ? palette.success.main
                                    : palette.grey[600]
                                }}
                              >
                                <Box
                                  sx={{
                                    width: 6,
                                    height: 6,
                                    borderRadius: "50%",
                                    backgroundColor: "currentColor",
                                    mr: 1
                                  }}
                                />
                                <Typography variant="caption" fontWeight="600">
                                  {option.isAvailable ? "Available" : "Coming Soon"}
                                </Typography>
                              </Box>
                            </Box>
                          </Stack>
                        </CardContent>
                      </Card>
                    </Grid>
                  ))}
                </Grid>
              </Box>
            );
          })}
        </Stack>
      </Stack>
    </Container>
  );
}
