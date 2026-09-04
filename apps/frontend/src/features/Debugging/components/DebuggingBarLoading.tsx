import { memo } from "react";

import { Box, IconButton, Stack, Typography, alpha, keyframes, useTheme } from "@mui/material";
import { motion } from "framer-motion";
import { HiX } from "react-icons/hi";

import type { AlertRuleOption } from "../debugging.type";

const shimmer = keyframes`
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
`;

const SEGMENT_COUNT = 100;

function DebuggingBarLoading({
  alertRule,
  onRemove,
  index = 0
}: {
  alertRule: AlertRuleOption;
  onRemove: () => void;
  index?: number;
}) {
  const { palette } = useTheme();

  return (
    <Stack
      component={motion.div}
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35, ease: "easeOut", delay: index * 0.05 }}
      direction="row"
      spacing={1}
      sx={{ borderRadius: 2, alignItems: "flex-start" }}
    >
      <Stack sx={{ alignItems: "flex-start", width: 170, cursor: "default", pt: 0.25 }}>
        <Typography
          sx={{
            textWrap: "nowrap",
            textOverflow: "ellipsis",
            width: "100%",
            overflow: "hidden",
            fontWeight: 500
          }}
        >
          {alertRule.name}
        </Typography>
        <Stack direction="row" spacing={0.75} sx={{ alignItems: "center", mt: 0.25 }}>
          <Typography
            variant="caption"
            color="textDisabled"
            sx={{ textTransform: "uppercase", lineHeight: 1 }}
          >
            ({alertRule.type})
          </Typography>
        </Stack>
      </Stack>

      <Stack sx={{ flex: 1, gap: 0.75 }}>
        <Box
          sx={{
            position: "relative",
            overflow: "hidden",
            borderRadius: 1,
            py: 0.25
          }}
        >
          <Stack direction="row" spacing={0.25} sx={{ width: "100%" }}>
            {Array.from({ length: SEGMENT_COUNT }).map((_, segmentIndex) => (
              <Box
                key={segmentIndex}
                component={motion.div}
                animate={{
                  opacity: [0.35, 0.75, 0.35]
                }}
                transition={{
                  duration: 1.6,
                  ease: "easeInOut",
                  repeat: Infinity,
                  delay: segmentIndex * 0.04
                }}
                sx={{
                  flex: 1,
                  height: 28,
                  borderRadius: 1,
                  bgcolor: alpha(palette.primary.main, 0.12)
                }}
              />
            ))}
          </Stack>

          <Box
            sx={{
              position: "absolute",
              inset: 0,
              background: `linear-gradient(90deg, transparent 0%, ${alpha(palette.common.white, 0.45)} 50%, transparent 100%)`,
              backgroundSize: "200% 100%",
              animation: `${shimmer} 1.8s linear infinite`,
              pointerEvents: "none",
              mixBlendMode: "soft-light"
            }}
          />
        </Box>

        <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
          <Box
            sx={{
              height: 8,
              width: 108,
              borderRadius: 1,
              bgcolor: alpha(palette.text.primary, 0.06)
            }}
          />
          <Typography variant="caption" color="primary" sx={{ fontWeight: 600 }}>
            Loading timeline...
          </Typography>
          <Box
            sx={{
              height: 8,
              width: 108,
              borderRadius: 1,
              bgcolor: alpha(palette.text.primary, 0.06)
            }}
          />
        </Stack>
      </Stack>

      <IconButton
        size="small"
        onClick={onRemove}
        aria-label={`Remove ${alertRule.name}`}
        sx={{ color: "text.secondary", mt: 0.25 }}
      >
        <HiX size={18} />
      </IconButton>
    </Stack>
  );
}

export default memo(DebuggingBarLoading);
