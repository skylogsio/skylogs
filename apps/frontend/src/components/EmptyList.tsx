import { ReactNode } from "react";

import { Box, Button, Stack, Typography, useTheme, alpha } from "@mui/material";
import { motion } from "framer-motion";
import { HiOutlinePlusSm } from "react-icons/hi";
import { IoIosArrowBack } from "react-icons/io";

interface EmptyListProps {
  icon: ReactNode;
  title: string;
  description: string;
  actionLabel?: string;
  onAction?: () => void;
  onBack?: () => void;
  minimal?: boolean;
  gradientColors?: [string, string];
}

export default function EmptyList({
  icon,
  title,
  description,
  actionLabel,
  onAction,
  onBack,
  minimal = false,
  gradientColors
}: EmptyListProps) {
  const { palette } = useTheme();

  const defaultGradientColors: [string, string] = [palette.primary.main, palette.primary.light];

  const finalGradientColors = gradientColors || defaultGradientColors;

  if (minimal) {
    return (
      <Stack
        component={motion.div}
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4, ease: "easeOut" }}
        spacing={2}
        sx={{
          alignItems: "center",
          justifyContent: "center",
          padding: 4,
          textAlign: "center"
        }}
      >
        <Box
          component={motion.div}
          initial={{ scale: 0, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          transition={{
            duration: 0.6,
            ease: [0.34, 1.56, 0.64, 1]
          }}
          sx={{ position: "relative" }}
        >
          <Box
            sx={{
              width: 90,
              height: 90,
              borderRadius: "50%",
              background: `linear-gradient(135deg, ${finalGradientColors[0]} 0%, ${finalGradientColors[1]} 100%)`,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              position: "relative",
              boxShadow: `0 8px 24px ${alpha(palette.primary.dark, 0.3)}`
            }}
          >
            <motion.div
              animate={{
                scale: [1, 1.05, 1],
                opacity: [1, 0.9, 1]
              }}
              transition={{
                duration: 2,
                ease: "easeInOut",
                repeat: Infinity
              }}
              style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "center"
              }}
            >
              {icon}
            </motion.div>
          </Box>
        </Box>
        <Typography
          variant="h6"
          sx={{
            fontWeight: 600,
            marginTop: 1
          }}
        >
          {title}
        </Typography>
        <Typography
          variant="body2"
          sx={{
            color: "text.disabled",
            maxWidth: 400,
            lineHeight: 1.6
          }}
        >
          {description}
        </Typography>
        {actionLabel && onAction && (
          <Box
            component={motion.div}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: "easeOut", delay: 0.2 }}
            sx={{ marginTop: 1 }}
          >
            <Button
              variant="contained"
              size="medium"
              startIcon={<HiOutlinePlusSm size="18px" />}
              onClick={onAction}
              sx={{
                paddingX: 3,
                paddingY: 1,
                textTransform: "none",
                fontWeight: 600
              }}
            >
              {actionLabel}
            </Button>
          </Box>
        )}
      </Stack>
    );
  }

  return (
    <Box
      component={motion.div}
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.6, ease: "easeOut" }}
      sx={{
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        minHeight: "80vh !important",
        padding: { xs: 3, sm: 6 },
        backgroundColor: "background.paper",
        borderRadius: 6,
        position: "relative",
        overflow: "hidden"
      }}
    >
      {onBack && (
        <Box
          sx={{
            position: "absolute",
            top: { xs: 16, sm: 24 },
            left: { xs: 16, sm: 24 },
            zIndex: 2
          }}
        >
          <Button
            onClick={onBack}
            sx={{
              paddingX: 2,
              textTransform: "none",
              backgroundColor: alpha(palette.primary.light, 0.08),
              "&:hover": {
                backgroundColor: alpha(palette.primary.light, 0.15)
              }
            }}
            startIcon={<IoIosArrowBack size="20px" />}
          >
            Back
          </Button>
        </Box>
      )}
      <Stack
        spacing={4}
        sx={{
          alignItems: "center",
          maxWidth: 520,
          position: "relative",
          zIndex: 1
        }}
      >
        <Box
          component={motion.div}
          initial={{ scale: 0, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          transition={{
            duration: 0.8,
            ease: [0.34, 1.56, 0.64, 1]
          }}
          sx={{ position: "relative" }}
        >
          <Box
            sx={{
              width: 140,
              height: 140,
              borderRadius: "50%",
              background: `linear-gradient(135deg, ${finalGradientColors[0]} 0%, ${finalGradientColors[1]} 100%)`,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              position: "relative",
              marginY: 4,
              boxShadow: `0 8px 20px ${alpha(palette.primary.dark, 0.4)}`
            }}
          >
            <motion.div
              animate={{
                scale: [1, 1.05, 1],
                opacity: [1, 0.9, 1]
              }}
              transition={{
                duration: 2,
                ease: "easeInOut",
                repeat: Infinity
              }}
              style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "center"
              }}
            >
              {icon}
            </motion.div>
          </Box>
        </Box>
        <Stack
          component={motion.div}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{
            duration: 0.8,
            ease: "easeOut",
            delay: 0.3
          }}
          spacing={2}
          sx={{
            alignItems: "center"
          }}
        >
          <Typography
            variant="h4"
            sx={{
              fontWeight: 700,
              textAlign: "center"
            }}
          >
            {title}
          </Typography>
          <Typography
            variant="body1"
            sx={{
              color: "text.disabled",
              textAlign: "center",
              maxWidth: 450,
              lineHeight: 1.7,
              fontSize: 16
            }}
          >
            {description}
          </Typography>
        </Stack>
        {actionLabel && onAction && (
          <Box
            component={motion.div}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{
              duration: 0.8,
              ease: "easeOut",
              delay: 0.5
            }}
          >
            <Button
              component={motion.button}
              whileHover={{
                y: -3
              }}
              whileTap={{
                y: -1
              }}
              transition={{
                type: "spring",
                stiffness: 400,
                damping: 17
              }}
              variant="contained"
              size="large"
              startIcon={<HiOutlinePlusSm size="22px" />}
              onClick={onAction}
              sx={{
                paddingX: 4,
                paddingY: 1.5,
                fontSize: 16,
                fontWeight: 600,
                borderRadius: 3,
                textTransform: "none",
                background: `linear-gradient(135deg, ${palette.primary.main} 0%, ${palette.primary.light} 100%)`,
                boxShadow: `0 8px 24px ${alpha(palette.primary.dark, 0.4)}`,
                "&:hover": {
                  background: `linear-gradient(135deg, ${palette.primary.dark} 0%, ${palette.primary.main} 100%)`
                }
              }}
            >
              {actionLabel}
            </Button>
          </Box>
        )}
      </Stack>
    </Box>
  );
}
