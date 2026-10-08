"use client";

import { useState } from "react";

import { Box, IconButton, Stack, Tooltip, Typography, alpha, useTheme } from "@mui/material";
import { BsCheck2, BsFillClipboard2Fill } from "react-icons/bs";
import { HiKey } from "react-icons/hi";

import type { IAlertRule } from "@/@types/alertRule";

interface AlertRuleApiTokenProps {
  token: NonNullable<IAlertRule["apiToken"]>;
}

export default function AlertRuleApiToken({ token }: AlertRuleApiTokenProps) {
  const { palette } = useTheme();
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    try {
      await window.navigator.clipboard.writeText(token);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.error("Unable to copy to clipboard.", err);
      alert("Copy to clipboard failed.");
    }
  }

  return (
    <Stack spacing={1} sx={{ width: "100%" }}>
      <Typography
        variant="subtitle1"
        sx={{
          fontWeight: "bold",
          color: palette.text.primary
        }}
      >
        API Token
      </Typography>
      <Stack
        direction="row"
        spacing={1}
        sx={{
          alignItems: "center",
          width: "100%",
          bgcolor: alpha(palette.secondary.main, 0.08),
          borderRadius: 2,
          paddingY: 1,
          paddingX: 1.5,
          border: 1,
          borderColor: alpha(palette.secondary.main, 0.25)
        }}
      >
        <Box
          sx={{
            display: "inline-flex",
            alignItems: "center",
            justifyContent: "center",
            lineHeight: 0,
            color: copied ? palette.success.main : palette.text.secondary
          }}
        >
          <HiKey size="1.1rem" />
        </Box>
        <Typography
          variant="body2"
          color={copied ? palette.success.main : palette.text.primary}
          sx={{
            flex: 1,
            minWidth: 0,
            fontFamily: "monospace",
            textWrap: "nowrap",
            overflow: "hidden",
            textOverflow: "ellipsis",
            transition: "color 0.2s ease-in-out"
          }}
        >
          {copied ? "Copied to clipboard!" : token}
        </Typography>
        <Tooltip title={copied ? "Copied!" : "Copy token"} placement="bottom" arrow>
          <IconButton
            size="small"
            onClick={handleCopy}
            aria-label={copied ? "Copied!" : "Copy API token"}
            sx={{
              color: copied ? palette.success.main : palette.text.secondary,
              transition: "color 0.2s ease-in-out, background-color 0.2s ease-in-out",
              "&:hover": {
                backgroundColor: alpha(copied ? palette.success.main : palette.text.secondary, 0.12)
              }
            }}
          >
            {copied ? <BsCheck2 size="1rem" /> : <BsFillClipboard2Fill size="1rem" />}
          </IconButton>
        </Tooltip>
      </Stack>
    </Stack>
  );
}
