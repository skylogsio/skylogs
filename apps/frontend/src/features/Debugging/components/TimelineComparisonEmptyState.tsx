import { Box, Stack, Typography, alpha, useTheme } from "@mui/material";
import { motion } from "framer-motion";
import { HiOutlineChartBar } from "react-icons/hi";

export default function TimelineComparisonEmptyState() {
  const { palette } = useTheme();

  return (
    <Box
      component={motion.div}
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45, ease: "easeOut" }}
      sx={{
        position: "relative",
        overflow: "hidden",
        borderRadius: 3,
        border: "1px dashed",
        borderColor: alpha(palette.primary.main, 0.22),
        bgcolor: alpha(palette.primary.main, 0.03),
        py: { xs: 4, sm: 5 },
        px: { xs: 2.5, sm: 4 }
      }}
    >
      <Box
        sx={{
          position: "absolute",
          inset: 0,
          background: `
            radial-gradient(circle at 15% 20%, ${alpha(palette.primary.main, 0.08)} 0%, transparent 42%),
            radial-gradient(circle at 85% 75%, ${alpha(palette.info.main, 0.07)} 0%, transparent 38%)
          `,
          pointerEvents: "none"
        }}
      />

      <Stack
        spacing={3}
        sx={{
          position: "relative",
          zIndex: 1,
          alignItems: "center",
          maxWidth: 520,
          mx: "auto"
        }}
      >
        <Box sx={{ position: "relative", width: 88, height: 88 }}>
          {[0, 1, 2].map((ring) => (
            <Box
              key={ring}
              component={motion.div}
              animate={{
                scale: [1, 1.35 + ring * 0.15],
                opacity: [0.35, 0]
              }}
              transition={{
                duration: 2.8,
                ease: "easeOut",
                repeat: Infinity,
                delay: ring * 0.7
              }}
              sx={{
                position: "absolute",
                inset: 0,
                borderRadius: "50%",
                border: `1px solid ${alpha(palette.primary.main, 0.35)}`
              }}
            />
          ))}

          <Box
            component={motion.div}
            animate={{
              scale: [1, 1.04, 1],
              boxShadow: [
                `0 0 0 0 ${alpha(palette.primary.main, 0.2)}`,
                `0 0 0 12px ${alpha(palette.primary.main, 0)}`,
                `0 0 0 0 ${alpha(palette.primary.main, 0)}`
              ]
            }}
            transition={{
              duration: 2.4,
              ease: "easeInOut",
              repeat: Infinity
            }}
            sx={{
              position: "relative",
              width: 88,
              height: 88,
              borderRadius: "50%",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              background: `linear-gradient(145deg, ${alpha(palette.primary.main, 0.18)} 0%, ${alpha(palette.primary.light, 0.08)} 100%)`,
              border: `1px solid ${alpha(palette.primary.main, 0.2)}`,
              color: palette.primary.main
            }}
          >
            <HiOutlineChartBar size={34} />
          </Box>
        </Box>

        <Stack spacing={1} sx={{ alignItems: "center", textAlign: "center" }}>
          <Typography variant="h6" sx={{ fontWeight: 700, letterSpacing: -0.2 }}>
            No alert rules selected
          </Typography>
          <Typography
            variant="body2"
            sx={{ color: "text.secondary", maxWidth: 380, lineHeight: 1.7 }}
          >
            Pick alert rules from the dropdown above to overlay their timelines and spot
            correlations across data sources.
          </Typography>
        </Stack>
      </Stack>
    </Box>
  );
}
