"use client";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Box,
  Button,
  IconButton,
  TextField,
  Typography,
  useTheme,
  useColorScheme,
  Stack
} from "@mui/material";
import { signIn } from "next-auth/react";
import { useForm } from "react-hook-form";
import { HiEye, HiEyeOff } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import { useScopedI18n } from "@/locales/client";

const signInSchema = z.object({
  username: z.string().trim().min(1, "RequiredUsername"),
  password: z.string().trim().min(1, "RequiredPassword")
});

type SignInFormType = z.infer<typeof signInSchema>;

export default function AuthenticationPage() {
  const router = useRouter();
  const { palette } = useTheme();
  const { systemMode, mode } = useColorScheme();
  const translate = useScopedI18n("auth");
  const globalTranslate = useScopedI18n("global");
  const {
    register,
    handleSubmit,
    formState: { errors }
  } = useForm<SignInFormType>({ resolver: zodResolver(signInSchema) });
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

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

  return (
    <Box
      sx={{
        backgroundImage: `url('/static/images/background-${systemMode || mode || "light"}.png')`,
        backgroundSize: "cover",
        backgroundRepeat: "no-repeat",
        backgroundPosition: "center center",
        width: "100vw",
        height: "100vh",
        display: "flex",
        flexDirection: "column",
        justifyContent: "space-between",
        alignItems: "center",
        padding: 2
      }}
    >
      <Stack
        sx={{
          backgroundColor: palette.background.paper,
          padding: 6,
          borderRadius: 4,
          marginY: "auto"
        }}
      >
        <Typography variant="h4" sx={{ textAlign: "center", marginBottom: 3 }}>
          {translate("Login to Account")}
        </Typography>
        <Typography variant="subtitle2" sx={{ marginBottom: 10 }}>
          {translate("Please enter your username and password to continue")}
        </Typography>
        <Stack
          sx={{ justifyContent: "flex-start", alignItems: "flex-end" }}
          component="form"
          onSubmit={handleSubmit(handleSubmitSignIn)}
        >
          <TextField
            variant="filled"
            size="medium"
            label={translate("Username")}
            sx={{ marginBottom: 1.5 }}
            error={!!errors.username}
            helperText={
              errors.username?.message
                ? translate(errors.username.message as "RequiredUsername")
                : undefined
            }
            disabled={loading}
            {...register("username")}
          />
          <TextField
            variant="filled"
            size="medium"
            label={translate("Password")}
            type={showPassword ? "text" : "password"}
            sx={{ WebkitTextSecurity: "*" }}
            slotProps={{
              input: {
                endAdornment: (
                  <IconButton disableRipple onClick={() => setShowPassword((prev) => !prev)}>
                    {showPassword ? (
                      <HiEyeOff color={palette.secondary.main} size={20} />
                    ) : (
                      <HiEye color={palette.secondary.main} size={20} />
                    )}
                  </IconButton>
                )
              }
            }}
            error={!!errors.password}
            helperText={
              errors.password?.message
                ? translate(errors.password.message as "RequiredPassword")
                : undefined
            }
            disabled={loading}
            {...register("password")}
          />
          <Button
            disableRipple
            size="small"
            sx={{
              textTransform: "none",
              width: "auto",
              backgroundColor: "transparent !important",
              transition: "all 200ms ease",
              "&:hover": {
                textDecoration: "underline"
              }
            }}
          >
            {translate("Forget Password")}
          </Button>
          <Button
            type="submit"
            variant="contained"
            fullWidth
            size="large"
            sx={{ marginTop: 6, paddingY: 1.4 }}
            loading={loading}
            loadingPosition="end"
          >
            {translate("Sign In")}
          </Button>
        </Stack>
      </Stack>
    </Box>
  );
}
