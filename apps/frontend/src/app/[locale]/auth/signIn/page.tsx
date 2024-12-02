"use client";
import { useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Box,
  Button,
  IconButton,
  TextField,
  Typography,
  useTheme,
  alpha,
  CircularProgress
} from "@mui/material";
import { signIn } from "next-auth/react";
import { useForm } from "react-hook-form";
import { HiEye, HiEyeOff } from "react-icons/hi";
import { toast } from "react-toastify";
import { z } from "zod";

import type { ServerResponseError } from "@/@types/global";
import TopBarLanguage from "@/components/Wrapper/TopBarLanguage";
import { useScopedI18n } from "@/locales/client";

const signInSchema = z.object({
  username: z.string({ required_error: "RequiredUsername" }).refine((data) => data.trim() !== "", {
    message: "RequiredUsername"
  }),
  password: z.string({ required_error: "RequiredPassword" }).refine((data) => data.trim() !== "", {
    message: "RequiredPassword"
  })
});

type SignInBody = z.infer<typeof signInSchema>;

export default function AuthenticationPage() {
  const translate = useScopedI18n("auth");
  const globalTranslate = useScopedI18n("global");
  const { palette } = useTheme();
  const {
    register,
    handleSubmit,
    formState: { errors }
  } = useForm<SignInBody>({ resolver: zodResolver(signInSchema) });
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  async function handleSubmitSignIn(body: SignInBody) {
    setLoading(true);
    const response = await signIn("credentials", {
      redirect: false,
      username: body.username,
      password: body.password
    });
    setLoading(false);
    let parsedResponse: ServerResponseError;
    try {
      if (response?.error === null) {
        window.location.reload();
        return;
      }
      parsedResponse = JSON.parse(response?.error ?? "");
      toast.error(parsedResponse.message);
    } catch (error) {
      console.log(error);
      toast.error(globalTranslate("SomethingWentWrongPleaseTryAgainLater"));
    }
  }

  return (
    <Box
      width="100vw"
      height="100vh"
      display="flex"
      flexDirection="column"
      justifyContent="space-between"
      alignItems="center"
      padding="1rem"
      sx={{
        backgroundImage: "url('/static/images/background.png')",
        backgroundSize: "cover",
        backgroundRepeat: "no-repeat",
        backgroundPosition: "center center"
      }}
    >
      <Box bgcolor="Background" padding="3rem" borderRadius="1rem" marginY="auto">
        <Typography variant="h4" textAlign="center" marginBottom="1.5rem">
          {translate("Login to Account")}
        </Typography>
        <Typography variant="subtitle2" marginTop="1rem" marginBottom="5rem">
          {translate("Please enter your username and password to continue")}
        </Typography>
        <Box
          display="flex"
          flexDirection="column"
          justifyContent="flex-start"
          alignItems="flex-end"
          component="form"
          onSubmit={handleSubmit(handleSubmitSignIn)}
        >
          <TextField
            variant="filled"
            size="medium"
            label={translate("Username")}
            sx={{ width: "100%", marginBottom: "0.8rem" }}
            error={!!errors.username}
            helperText={
              errors.username?.message
                ? translate(errors.username.message as "RequiredUsername")
                : undefined
            }
            {...register("username")}
          />
          <TextField
            variant="filled"
            size="medium"
            label={translate("Password")}
            type={showPassword ? "text" : "password"}
            sx={{ width: "100%", WebkitTextSecurity: "*" }}
            slotProps={{
              input: {
                endAdornment: (
                  <IconButton disableRipple onClick={() => setShowPassword((prev) => !prev)}>
                    {showPassword ? (
                      <HiEyeOff color={palette.secondary.main} size="1.2rem" />
                    ) : (
                      <HiEye color={palette.secondary.main} size="1.2rem" />
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
            sx={{ marginTop: "3rem !important", paddingY: "0.7rem" }}
            disabled={loading}
            startIcon={
              loading ? <CircularProgress sx={{ color: "inherit" }} size="1rem" /> : undefined
            }
          >
            {translate("Sign In")}
          </Button>
        </Box>
      </Box>
      <Box
        style={{ marginRight: "auto" }}
        sx={{
          backgroundColor: ({ palette }) => alpha(palette.background.paper, 0.3),
          padding: "0.6rem 0",
          borderRadius: "0.6rem"
        }}
      >
        <TopBarLanguage />
      </Box>
    </Box>
  );
}
