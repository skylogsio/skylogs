"use client";

import { useState } from "react";

import { Box, Typography, Stack, Button, useTheme } from "@mui/material";
import { HiCalendar, HiChevronDown, HiOutlineInformationCircle } from "react-icons/hi";

import AnalysisTimeRangePopover from "@/components/Debugging/DebuggingTimeRangePopover";

export default function AnalysisPage() {
  const { palette } = useTheme();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);

  const handleOpenPicker = (event: React.MouseEvent<HTMLButtonElement>) => {
    setAnchorEl(event.currentTarget);
  };

  const handleClosePicker = () => {
    setAnchorEl(null);
  };

  return (
    <Stack spacing={3} sx={{ px: 2, py: 1, width: "100%", boxSizing: "border-box" }}>
      <Box
        sx={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          flexWrap: "wrap",
          gap: 2
        }}
      >
        <Box>
          <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
            <Typography variant="h5" sx={{ fontWeight: 700, color: "text.primary" }}>
              Debugging & Root Cause Analysis
            </Typography>
            <HiOutlineInformationCircle
              size={20}
              style={{ color: palette.text.disabled, cursor: "pointer" }}
            />
          </Stack>
          <Typography variant="body2" sx={{ mt: 0.5, color: "text.secondary" }}>
            Correlate alerts across data sources and analyze the root cause with AI.
          </Typography>
        </Box>

        <Stack direction="row" spacing={1.5} sx={{ alignItems: "center" }}>
          <Button
            onClick={handleOpenPicker}
            variant="contained"
            startIcon={<HiCalendar size={20} color={palette.text.disabled} />}
            endIcon={<HiChevronDown size={18} />}
            sx={{
              textTransform: "none",
              color: "text.primary",
              borderRadius: "8px",
              height: "40px",
              px: 2,
              fontWeight: 500,
              bgcolor: "background.paper"
            }}
          >
            Last 15 Minutes
          </Button>

          <AnalysisTimeRangePopover
            anchorEl={anchorEl}
            open={Boolean(anchorEl)}
            onClose={handleClosePicker}
          />

          <Stack
            direction="row"
            spacing={1}
            sx={{
              alignItems: "center",
              borderRadius: "8px",
              height: "40px",
              px: 2,
              bgcolor: "background.paper"
            }}
          >
            <Typography
              variant="body2"
              sx={{ fontFamily: "monospace", color: "text.primary", fontSize: "0.85rem" }}
            >
              2024/10/27 20:48:00
            </Typography>
            <Typography variant="body2" color="textDisabled">
              →
            </Typography>
            <Typography
              variant="body2"
              sx={{ fontFamily: "monospace", color: "text.primary", fontSize: "0.85rem" }}
            >
              2024/10/27 21:03:00
            </Typography>
            <HiCalendar size={20} style={{ color: palette.text.disabled, marginLeft: 10 }} />
          </Stack>
        </Stack>
      </Box>
    </Stack>
  );
}
