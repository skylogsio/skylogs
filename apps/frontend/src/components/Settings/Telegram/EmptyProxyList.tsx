import { Box, Button, Stack, Typography, useTheme, alpha } from "@mui/material";
import { motion } from "framer-motion";
import { HiOutlinePlusSm } from "react-icons/hi";
import { RiTelegram2Fill } from "react-icons/ri";

interface EmptyProxyListProps {
  onCreate: () => void;
}

export default function EmptyProxyList({ onCreate }: EmptyProxyListProps) {
  const { palette } = useTheme();
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
        borderRadius: "1.5rem",
        position: "relative",
        overflow: "hidden"
      }}
    >
      <Stack
        spacing={4}
        alignItems="center"
        maxWidth="520px"
        sx={{ position: "relative", zIndex: 1 }}
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
          <motion.div
            animate={{
              scale: [1, 1, 1.3],
              opacity: [0, 1, 0]
            }}
            transition={{
              duration: 2,
              ease: "easeOut",
              repeat: Infinity
            }}
            style={{
              position: "absolute",
              top: "50%",
              left: "50%",
              translateX: "-50%",
              translateY: "-50%",
              width: "140px",
              height: "140px",
              borderRadius: "50%",
              border: `3px solid ${alpha(palette.primary.dark, 0.4)}`,
              pointerEvents: "none"
            }}
          />
          <Box
            sx={{
              width: "140px",
              height: "140px",
              borderRadius: "50%",
              background: "linear-gradient(135deg, #2AABEE 0%, #229ED9 100%)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              position: "relative",
              marginY: "2rem",
              boxShadow: `0 10px 40px ${alpha(palette.primary.dark, 0.4)}`
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
            >
              <RiTelegram2Fill size="5.5rem" color={palette.common.white} />
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
          alignItems="center"
        >
          <Typography
            variant="h4"
            fontWeight="700"
            textAlign="center"
            sx={{ letterSpacing: "-0.5px" }}
          >
            No Proxies Configured
          </Typography>
          <Typography
            variant="body1"
            color="text.disabled"
            textAlign="center"
            sx={{
              maxWidth: "450px",
              lineHeight: 1.7,
              fontSize: "1rem"
            }}
          >
            Set up your first Telegram proxy to enable secure and reliable message delivery. Proxies
            help ensure your notifications reach users even in restricted networks.
          </Typography>
        </Stack>
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
            onClick={onCreate}
            sx={{
              paddingX: 4,
              paddingY: 1.5,
              fontSize: "1rem",
              fontWeight: 600,
              borderRadius: "12px",
              textTransform: "none",
              background: `linear-gradient(135deg, ${palette.primary.main} 0%, ${palette.primary.light} 100%)`,
              boxShadow: `0 8px 24px ${alpha(palette.primary.dark, 0.4)}`,
              "&:hover": {
                background: `linear-gradient(135deg, ${palette.primary.dark} 0%, ${palette.primary.main} 100%)`
              }
            }}
          >
            Create First Proxy
          </Button>
        </Box>
      </Stack>
    </Box>
  );
}
