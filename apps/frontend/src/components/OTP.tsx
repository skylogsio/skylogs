"use client";

import { inputBaseClasses, TextField } from "@mui/material";
import { styled } from "@mui/material/styles";

const OTP = styled(TextField)(({ theme }) => ({
  [`& .${inputBaseClasses.input}`]: {
    letterSpacing: `${theme.spacing(3)}!important`,
    fontSize: "1.2rem !important",
    textAlign: "center !important",
    padding: `${theme.spacing(1.777)}!important`
  }
}));

export default OTP;
