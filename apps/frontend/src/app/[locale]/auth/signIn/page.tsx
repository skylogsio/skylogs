"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Box,
  Button,
  CircularProgress,
  IconButton,
  TextField,
  Typography,
  useTheme,
  Stack,
  alpha,
  Menu,
  MenuItem,
  ListItemIcon,
  ListItemText
} from "@mui/material";
import { motion, useReducedMotion } from "framer-motion";
import { signIn } from "next-auth/react";
import { useForm } from "react-hook-form";
import { HiEye, HiEyeOff } from "react-icons/hi";
import { IoChevronDown, IoMoon, IoSunny } from "react-icons/io5";
import { TbBrightnessFilled } from "react-icons/tb";
import { toast } from "react-toastify";
import { z } from "zod";

import { useCurrentTheme, type ThemePreference } from "@/hooks";
import { useScopedI18n } from "@/locales/client";

const signInSchema = z.object({
  username: z.string().trim().min(1, "RequiredUsername"),
  password: z.string().trim().min(1, "RequiredPassword")
});

type SignInFormType = z.infer<typeof signInSchema>;

const easeOut = [0.22, 1, 0.36, 1] as const;

const themeOptions: {
  mode: ThemePreference;
  label: string;
  description: string;
  icon: React.ReactNode;
}[] = [
  {
    mode: "light",
    label: "Light",
    description: "Bright and clear",
    icon: <IoSunny size={18} />
  },
  {
    mode: "dark",
    label: "Dark",
    description: "Easy on the eyes",
    icon: <IoMoon size={18} />
  },
  {
    mode: "system",
    label: "System",
    description: "Match device setting",
    icon: <TbBrightnessFilled size={18} />
  }
];

export default function AuthenticationPage() {
  const router = useRouter();
  const { palette } = useTheme();
  const { preference, isDark, setMode } = useCurrentTheme();
  const reduceMotion = useReducedMotion();
  const translate = useScopedI18n("auth");
  const globalTranslate = useScopedI18n("global");
  const {
    register,
    handleSubmit,
    formState: { errors }
  } = useForm<SignInFormType>({
    resolver: zodResolver(signInSchema),
    mode: "onSubmit"
  });
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [themeMenuAnchor, setThemeMenuAnchor] = useState<null | HTMLElement>(null);

  const activeThemeOption =
    themeOptions.find((option) => option.mode === preference) ?? themeOptions[0];
  const themeMenuOpen = Boolean(themeMenuAnchor);

  async function handleSubmitSignIn(body: SignInFormType) {
    setLoading(true);

    try {
      const response = await signIn("credentials", {
        redirect: false,
        username: body.username,
        password: body.password
      });

      setLoading(false);

      if (response?.error) {
        toast.error(response.error);
        return;
      }

      if (response?.ok) {
        toast.success("Login successful!");
        router.replace("/alert-rule");
        router.refresh();
      }
    } catch (error) {
      setLoading(false);
      console.error("Sign in error:", error);
      toast.error(globalTranslate("SomethingWentWrongPleaseTryAgainLater"));
    }
  }

  function handleThemeSelect(selectedMode: ThemePreference) {
    setMode(selectedMode);
    setThemeMenuAnchor(null);
  }

  const ambientOrbs = [
    {
      size: 480,
      top: "-18%",
      left: "-12%",
      color: alpha(palette.primary.main, isDark ? 0.18 : 0.28),
      duration: 20,
      x: [0, 36, -18, 0],
      y: [0, 28, 8, 0]
    },
    {
      size: 360,
      top: "48%",
      right: "-14%",
      color: alpha(palette.secondary.main, isDark ? 0.1 : 0.4),
      duration: 24,
      x: [0, -30, 22, 0],
      y: [0, -22, 14, 0]
    },
    {
      size: 260,
      bottom: "4%",
      left: "22%",
      color: alpha(palette.primary.dark, isDark ? 0.14 : 0.14),
      duration: 17,
      x: [0, 18, -12, 0],
      y: [0, -26, 10, 0]
    }
  ];

  return (
    <Box
      sx={{
        position: "relative",
        overflow: "hidden",
        width: "100vw",
        height: "100vh",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        padding: { xs: 2, sm: 3 },
        backgroundColor: palette.background.default,
        backgroundImage: isDark
          ? `radial-gradient(ellipse 80% 60% at 20% 10%, ${alpha(palette.primary.main, 0.16)}, transparent 55%),
             radial-gradient(ellipse 70% 50% at 90% 80%, ${alpha("#3A2E22", 0.45)}, transparent 50%)`
          : `radial-gradient(ellipse 80% 55% at 15% 0%, ${alpha(palette.secondary.light, 0.95)}, transparent 50%),
             radial-gradient(ellipse 70% 50% at 100% 100%, ${alpha(palette.primary.main, 0.22)}, transparent 55%)`
      }}
    >
      {!reduceMotion &&
        ambientOrbs.map((orb, index) => (
          <Box
            key={index}
            component={motion.div}
            animate={{ x: orb.x, y: orb.y, scale: [1, 1.06, 0.97, 1] }}
            transition={{
              duration: orb.duration,
              repeat: Infinity,
              ease: "easeInOut"
            }}
            sx={{
              position: "absolute",
              width: orb.size,
              height: orb.size,
              top: orb.top,
              left: orb.left,
              right: orb.right,
              bottom: orb.bottom,
              borderRadius: "50%",
              background: orb.color,
              filter: "blur(56px)",
              pointerEvents: "none"
            }}
          />
        ))}

      <Box
        component={motion.div}
        initial={reduceMotion ? false : { opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5, delay: 0.15, ease: easeOut }}
        sx={{
          position: "absolute",
          top: { xs: 16, sm: 24 },
          right: { xs: 16, sm: 24 },
          zIndex: 2
        }}
      >
        <Button
          onClick={(event) => setThemeMenuAnchor(event.currentTarget)}
          aria-haspopup="menu"
          aria-expanded={themeMenuOpen}
          aria-label="Change theme"
          startIcon={activeThemeOption.icon}
          endIcon={
            <Box
              component={motion.span}
              animate={{ rotate: themeMenuOpen ? 180 : 0 }}
              transition={{ duration: 0.2 }}
              sx={{ display: "flex", lineHeight: 0 }}
            >
              <IoChevronDown size={14} />
            </Box>
          }
          sx={{
            textTransform: "none",
            px: 1.75,
            py: 1,
            borderRadius: 2.5,
            minWidth: 132,
            justifyContent: "space-between",
            color: palette.text.primary,
            backgroundColor: alpha(palette.background.paper, isDark ? 0.86 : 0.94),
            backdropFilter: "blur(14px)",
            border: `1px solid ${alpha(palette.primary.main, isDark ? 0.22 : 0.35)}`,
            boxShadow: `0 8px 24px ${alpha("#0E0D0C", isDark ? 0.4 : 0.08)}`,
            "&:hover": {
              backgroundColor: alpha(palette.background.paper, isDark ? 0.95 : 1)
            },
            "& .MuiButton-startIcon": {
              color: palette.primary.main,
              marginInlineEnd: 1
            },
            "& .MuiButton-endIcon": {
              color: palette.text.secondary,
              marginInlineStart: 1
            }
          }}
        >
          <Box sx={{ textAlign: "start", lineHeight: 1.2 }}>
            <Typography
              component="span"
              variant="caption"
              sx={{
                display: "block",
                color: palette.text.secondary,
                fontSize: "0.65rem",
                letterSpacing: "0.04em",
                textTransform: "uppercase"
              }}
            >
              Theme
            </Typography>
            <Typography component="span" variant="body2" sx={{ fontWeight: 600 }}>
              {activeThemeOption.label}
            </Typography>
          </Box>
        </Button>

        <Menu
          anchorEl={themeMenuAnchor}
          open={themeMenuOpen}
          onClose={() => setThemeMenuAnchor(null)}
          anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
          transformOrigin={{ vertical: "top", horizontal: "right" }}
          slotProps={{
            paper: {
              sx: {
                mt: 1,
                minWidth: 220,
                borderRadius: 2.5,
                border: `1px solid ${palette.divider}`,
                backgroundColor: palette.background.paper,
                boxShadow: `0 12px 32px ${alpha("#0E0D0C", isDark ? 0.45 : 0.12)}`
              }
            }
          }}
        >
          {themeOptions.map(({ mode: themeMode, label, description, icon }) => {
            const selected = preference === themeMode;
            return (
              <MenuItem
                key={themeMode}
                selected={selected}
                onClick={() => handleThemeSelect(themeMode)}
                sx={{
                  py: 1.25,
                  px: 1.5,
                  gap: 0.5,
                  borderRadius: 1.5,
                  mx: 0.75,
                  my: 0.25,
                  "&.Mui-selected": {
                    backgroundColor: alpha(palette.primary.main, 0.16),
                    "&:hover": {
                      backgroundColor: alpha(palette.primary.main, 0.24)
                    }
                  }
                }}
              >
                <ListItemIcon
                  sx={{
                    minWidth: 36,
                    color: selected ? palette.primary.main : palette.text.secondary
                  }}
                >
                  {icon}
                </ListItemIcon>
                <ListItemText
                  primary={label}
                  secondary={description}
                  slotProps={{
                    primary: {
                      sx: {
                        fontWeight: selected ? 700 : 500,
                        color: selected ? palette.primary.main : palette.text.primary
                      }
                    },
                    secondary: {
                      sx: { fontSize: "0.75rem" }
                    }
                  }}
                />
              </MenuItem>
            );
          })}
        </Menu>
      </Box>

      <Box
        component={motion.div}
        initial={reduceMotion ? false : { opacity: 0, y: 28, scale: 0.98 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.7, ease: easeOut }}
        sx={{
          position: "relative",
          zIndex: 1,
          width: "100%",
          maxWidth: 420,
          overflow: "hidden",
          borderRadius: 4,
          backgroundColor: alpha(palette.background.paper, isDark ? 0.94 : 0.98),
          border: `1px solid ${alpha(palette.primary.main, isDark ? 0.2 : 0.24)}`,
          boxShadow: `0 28px 64px ${alpha("#0E0D0C", isDark ? 0.5 : 0.1)}`
        }}
      >
        <Stack
          component={motion.div}
          initial={reduceMotion ? false : { opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.55, delay: 0.08, ease: easeOut }}
          spacing={1.75}
          sx={{
            alignItems: "center",
            px: { xs: 3, sm: 4 },
            pt: { xs: 3.5, sm: 4 },
            pb: { xs: 2.5, sm: 3 }
          }}
        >
          <Box
            sx={{
              width: { xs: 168, sm: 196 },
              aspectRatio: "3 / 2",
              borderRadius: 3,
              backgroundColor: "transparent",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              overflow: "hidden",
              px: 1.5
            }}
          >
            <Box
              component="img"
              src="/static/images/logo.png"
              alt="Skylogs"
              sx={{
                width: "100%",
                height: "auto",
                display: "block",
                objectFit: "contain"
              }}
            />
          </Box>

          <Stack spacing={0.75} sx={{ alignItems: "center" }}>
            <Typography
              component="h1"
              sx={{
                color: isDark ? palette.secondary.main : palette.text.primary,
                fontWeight: 700,
                fontSize: { xs: "1.35rem", sm: "1.5rem" },
                letterSpacing: "0.28em",
                textTransform: "uppercase",
                textAlign: "center",
                lineHeight: 1
              }}
            >
              Skylogs
            </Typography>
            <Typography
              variant="body2"
              sx={{
                color: palette.text.secondary,
                textAlign: "center",
                letterSpacing: "0.02em",
                maxWidth: 300,
                lineHeight: 1.55
              }}
            >
              {translate("Please enter your username and password to continue")}
            </Typography>
          </Stack>
        </Stack>

        <Box
          sx={{
            px: { xs: 3, sm: 4.5 },
            pt: 3,
            pb: { xs: 4, sm: 4.5 },
            borderTop: `1px solid ${alpha(palette.primary.main, isDark ? 0.12 : 0.14)}`
          }}
        >
          <Typography
            variant="h5"
            sx={{
              mb: 2.5,
              fontWeight: 700,
              letterSpacing: "-0.02em",
              color: palette.text.primary,
              textAlign: "center"
            }}
          >
            {translate("Login to Account")}
          </Typography>

          <Stack component="form" onSubmit={handleSubmit(handleSubmitSignIn)} spacing={0}>
            <Box
              component={motion.div}
              initial={reduceMotion ? false : { opacity: 0, y: 14 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, delay: 0.22, ease: easeOut }}
            >
              <TextField
                variant="filled"
                size="medium"
                label={translate("Username")}
                sx={{ mb: 1.5 }}
                error={!!errors.username}
                helperText={
                  errors.username?.message
                    ? translate(errors.username.message as "RequiredUsername")
                    : undefined
                }
                disabled={loading}
                autoComplete="username"
                {...register("username")}
              />
            </Box>

            <Box
              component={motion.div}
              initial={reduceMotion ? false : { opacity: 0, y: 14 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, delay: 0.32, ease: easeOut }}
            >
              <TextField
                type={showPassword ? "text" : "password"}
                variant="filled"
                size="medium"
                label={translate("Password")}
                sx={{ mb: 0.5 }}
                error={!!errors.password}
                helperText={
                  errors.password?.message
                    ? translate(errors.password.message as "RequiredPassword")
                    : undefined
                }
                slotProps={{
                  input: {
                    endAdornment: (
                      <IconButton
                        disableRipple
                        aria-label={showPassword ? "Hide password" : "Show password"}
                        onClick={() => setShowPassword((prev) => !prev)}
                      >
                        {showPassword ? (
                          <HiEyeOff color={palette.grey[400]} size={20} />
                        ) : (
                          <HiEye color={palette.grey[400]} size={20} />
                        )}
                      </IconButton>
                    )
                  }
                }}
                disabled={loading}
                autoComplete="current-password"
                {...register("password")}
              />
            </Box>

            <Box
              component={motion.div}
              initial={reduceMotion ? false : { opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.45, delay: 0.4, ease: easeOut }}
              sx={{ display: "flex", justifyContent: "flex-end" }}
            >
              <Button
                disableRipple
                size="small"
                sx={{
                  textTransform: "none",
                  width: "auto",
                  px: 0.5,
                  backgroundColor: "transparent !important",
                  color: palette.text.secondary,
                  transition: "color 200ms ease",
                  "&:hover": {
                    color: palette.primary.main,
                    textDecoration: "underline"
                  }
                }}
              >
                {translate("Forget Password")}
              </Button>
            </Box>

            <Box
              component={motion.div}
              initial={reduceMotion ? false : { opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.55, delay: 0.48, ease: easeOut }}
              whileHover={reduceMotion || loading ? undefined : { scale: 1.015 }}
              whileTap={reduceMotion || loading ? undefined : { scale: 0.985 }}
              sx={{ mt: 3 }}
            >
              <Button
                type="submit"
                variant="contained"
                fullWidth
                size="large"
                disabled={loading}
                aria-busy={loading}
                sx={{
                  position: "relative",
                  py: 1.5,
                  minHeight: 52,
                  fontWeight: 600,
                  letterSpacing: "0.04em",
                  color: palette.primary.contrastText,
                  background: `linear-gradient(135deg, ${palette.secondary.main} 0%, ${palette.primary.main} 100%)`,
                  transition: "filter 220ms ease, opacity 220ms ease",
                  overflow: "hidden",
                  "&.Mui-disabled": {
                    color: palette.primary.contrastText,
                    background: `linear-gradient(135deg, ${palette.secondary.main} 0%, ${palette.primary.main} 100%)`,
                    opacity: 0.92
                  },
                  "&:hover": {
                    filter: loading ? "none" : "brightness(1.04)",
                    background: `linear-gradient(135deg, ${palette.secondary.main} 0%, ${palette.primary.main} 100%)`
                  }
                }}
              >
                {loading ? (
                  <>
                    <Stack
                      direction="row"
                      spacing={1.25}
                      sx={{
                        alignItems: "center",
                        justifyContent: "center"
                      }}
                    >
                      <CircularProgress
                        size={18}
                        thickness={5}
                        sx={{ color: palette.primary.contrastText }}
                      />
                      <Typography
                        component="span"
                        sx={{
                          fontWeight: 600,
                          letterSpacing: "0.04em",
                          fontSize: "0.95rem"
                        }}
                      >
                        {translate("Signing In")}
                      </Typography>
                    </Stack>
                    {!reduceMotion && (
                      <Box
                        component={motion.div}
                        animate={{ x: ["-120%", "120%"] }}
                        transition={{ duration: 1.4, repeat: Infinity, ease: "easeInOut" }}
                        sx={{
                          position: "absolute",
                          top: 0,
                          bottom: 0,
                          width: "45%",
                          background: `linear-gradient(90deg, transparent, ${alpha("#FFFFFF", 0.28)}, transparent)`,
                          pointerEvents: "none"
                        }}
                      />
                    )}
                  </>
                ) : (
                  <Typography component="span" sx={{ fontWeight: 600, letterSpacing: "0.04em" }}>
                    {translate("Sign In")}
                  </Typography>
                )}
              </Button>
            </Box>
          </Stack>
        </Box>
      </Box>
    </Box>
  );
}
