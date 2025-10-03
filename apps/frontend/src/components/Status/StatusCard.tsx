import { useRouter } from "next/navigation";
import { useState } from "react";

import {
  Card,
  CardContent,
  Typography,
  Box,
  Chip,
  IconButton,
  Stack,
  Collapse,
  MenuItem,
  ListItemIcon,
  ListItemText,
  Menu
} from "@mui/material";
import { styled, keyframes, useTheme, alpha } from "@mui/material/styles";
import { BsThreeDots } from "react-icons/bs";
import { HiPencil, HiTrash } from "react-icons/hi";
import { TbCircleCheck, TbAlertTriangle, TbExclamationCircle } from "react-icons/tb";

import type { IStatusCard, StateType } from "@/@types/status";
import { formatTimeAgo } from "@/utils/general";

const pulse = keyframes`
  0% { opacity: 1; }
  50% { opacity: 0.6; }
  100% { opacity: 1; }
`;

const glow = keyframes`
  0% { box-shadow: 0 0 5px rgba(255, 193, 7, 0.3); }
  50% { box-shadow: 0 0 20px rgba(255, 193, 7, 0.6); }
  100% { box-shadow: 0 0 5px rgba(255, 193, 7, 0.3); }
`;

const criticalGlow = keyframes`
  0% { box-shadow: 0 0 5px rgba(244, 67, 54, 0.3); }
  50% { box-shadow: 0 0 20px rgba(244, 67, 54, 0.6); }
  100% { box-shadow: 0 0 5px rgba(244, 67, 54, 0.3); }
`;

const shimmer = keyframes`
  0% { background-position: -200px 0; }
  100% { background-position: calc(200px + 100%) 0; }
`;

const StateCard = styled(Card)<{ state: StateType }>(({ theme, state }) => ({
  width: 340,
  background:
    state === "resolved"
      ? "linear-gradient(135deg, #e8f5e8 0%, #cfead0 100%)"
      : state === "warning"
        ? "linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%)"
        : "linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%)",
  border: `2px solid ${
    state === "resolved"
      ? theme.palette.success.main
      : state === "warning"
        ? theme.palette.warning.main
        : theme.palette.error.main
  }`,
  borderRadius: 16,
  position: "relative",
  overflow: "hidden",
  transition: "all 0.3s ease-in-out",
  cursor: "pointer",
  animation:
    state === "warning"
      ? `${glow} 2s infinite ease-in-out`
      : state === "critical"
        ? `${criticalGlow} 1.5s infinite ease-in-out`
        : "none",
  "&:hover": {
    transform: "translateY(-4px) scale(1.02)",
    boxShadow: theme.shadows[8]
  },
  "&::before": {
    content: "''",
    position: "absolute",
    top: 0,
    left: "-200px",
    width: "200px",
    height: "100%",
    background: "linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent)",
    animation: `${shimmer} 3s infinite`
  }
}));

const StateIcon = styled(Box)<{ state: StateType }>(({ theme, state }) => ({
  display: "flex",
  alignItems: "center",
  justifyContent: "center",
  width: 40,
  height: 40,
  borderRadius: "50%",
  backgroundColor:
    state === "resolved"
      ? theme.palette.success.main
      : state === "warning"
        ? theme.palette.warning.main
        : theme.palette.error.main,
  color: "white",
  animation: state !== "resolved" ? `${pulse} 2s infinite ease-in-out` : "none"
}));

const CounterChip = styled(Chip)<{ severity: "warning" | "critical" }>(({ theme, severity }) => ({
  height: 24,
  fontSize: "0.75rem",
  fontWeight: 600,
  backgroundColor: severity === "critical" ? theme.palette.error.main : theme.palette.warning.main,
  color: "white",
  "& .MuiChip-label": {
    padding: "0 8px"
  }
}));

function getStatusIcon(state: StateType) {
  switch (state) {
    case "resolved":
      return <TbCircleCheck size={20} />;
    case "warning":
      return <TbAlertTriangle size={20} />;
    case "critical":
      return <TbExclamationCircle size={20} />;
    default:
      return <TbCircleCheck size={20} />;
  }
}

interface StatusMonitoringCardsProps {
  info: IStatusCard;
  alertRulePagePath?: string;
  onEdit?: (statusCard: IStatusCard) => void;
  onDelete?: (statusCard: IStatusCard) => void;
}

const StatusMonitoringCards = ({
  info,
  alertRulePagePath = "/alert-rule",
  onDelete,
  onEdit
}: StatusMonitoringCardsProps) => {
  const { palette } = useTheme();
  const router = useRouter();
  const [showThreeDots, setShowThreeDots] = useState(false);
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const open = Boolean(anchorEl);

  function handleMenuClick(event: React.MouseEvent<HTMLElement>) {
    event.stopPropagation();
    setAnchorEl(event.currentTarget);
  }

  function handleMenuClose(event: React.MouseEvent<HTMLElement>) {
    event.stopPropagation();
    setAnchorEl(null);
  }

  function handleEdit(event: React.MouseEvent<HTMLElement>) {
    event.stopPropagation();
    handleMenuClose(event);
    onEdit?.(info);
  }

  function handleDelete(event: React.MouseEvent<HTMLElement>) {
    event.stopPropagation();
    handleMenuClose(event);
    onDelete?.(info);
  }

  function handleCardClick(tags: IStatusCard["tags"], state: IStatusCard["state"]) {
    const filters: Record<string, unknown> = {};

    if (tags && tags.length > 0) {
      filters.tags = tags;
    }

    if (state === "critical") {
      filters.status = "critical";
    } else if (state === "warning") {
      filters.status = "warning";
    }

    const searchParams = new URLSearchParams();
    if (Object.keys(filters).length > 0) {
      searchParams.set("filters", encodeURIComponent(JSON.stringify(filters)));
    }

    const url = `${alertRulePagePath}${searchParams.toString() ? `?${searchParams.toString()}` : ""}`;

    router.push(url);
  }

  function handleAlertTagClick(
    event: React.MouseEvent<HTMLElement>,
    tags: IStatusCard["tags"],
    state: IStatusCard["state"],
    alertsTag: string
  ) {
    event.stopPropagation();
    const allTags = [...tags, alertsTag];
    handleCardClick(allTags, state);
  }

  return (
    <Box position="relative" maxHeight="136px" zIndex={showThreeDots ? 999 : "none"}>
      <StateCard
        key={info.id}
        state={info.state}
        onClick={() => handleCardClick(info.tags, info.state)}
        onMouseEnter={() => setShowThreeDots(true)}
        onMouseLeave={() => setShowThreeDots(false)}
      >
        <CardContent
          sx={{ padding: "16px !important", height: "100%", position: "relative", zIndex: 1 }}
        >
          <Stack width="100%" direction="row" justifyContent="space-between" alignItems="center">
            <Typography
              variant="h6"
              sx={{
                mr: "auto !important",
                fontWeight: 700,
                fontSize: "1.1rem",
                color: palette.grey[800],
                lineHeight: 1.2,
                maxWidth: "160px"
              }}
            >
              {info.name}
            </Typography>
            <StateIcon state={info.state}>{getStatusIcon(info.state)}</StateIcon>
            <Collapse orientation="horizontal" in={showThreeDots}>
              <IconButton
                sx={{
                  ml: ({ spacing }) => `${spacing(2)}!important`,
                  backgroundColor: "rgba(255, 255, 255, 0.4)",
                  padding: 1.1,
                  transition: "all 300ms ease",
                  "&:hover": {
                    backgroundColor: "rgba(255, 255, 255, 0.7)"
                  }
                }}
                onClick={handleMenuClick}
                size="large"
              >
                <BsThreeDots size="1.3rem" />
              </IconButton>
            </Collapse>
          </Stack>
          <Box sx={{ display: "flex", gap: 1, mb: 2, flexWrap: "wrap" }}>
            {info.criticalCount > 0 && (
              <CounterChip
                label={`${info.criticalCount} Critical`}
                severity="critical"
                size="small"
              />
            )}
            {info.warningCount > 0 && (
              <CounterChip label={`${info.warningCount} Warning`} severity="warning" size="small" />
            )}
            {info.criticalCount === 0 && info.warningCount === 0 && (
              <Chip
                label="All Systems OK"
                size="small"
                sx={{
                  backgroundColor: palette.success.main,
                  color: "white",
                  fontWeight: 600
                }}
              />
            )}
          </Box>

          <Collapse in={showThreeDots}>
            <Box sx={{ mb: 1.5 }}>
              <Typography
                variant="caption"
                sx={{ color: palette.grey[600], fontWeight: 500, mb: 0.5, display: "block" }}
              >
                Tags
              </Typography>
              <Box sx={{ display: "flex", gap: 0.5, flexWrap: "wrap" }}>
                {info.tags.map((tag, index) => (
                  <Chip
                    key={index}
                    label={tag}
                    size="small"
                    variant="outlined"
                    sx={{
                      height: 20,
                      fontSize: "0.65rem",
                      borderColor: palette.grey[400],
                      color: palette.grey[600]
                    }}
                  />
                ))}
              </Box>
            </Box>
            {info.alertsTags?.length > 0 && (
              <Box sx={{ mb: 1.5 }}>
                <Typography
                  variant="caption"
                  sx={{ color: palette.grey[600], fontWeight: 500, mb: 0.5, display: "block" }}
                >
                  Alerts Tags
                </Typography>
                <Box sx={{ display: "flex", gap: 0.5, flexWrap: "wrap" }}>
                  {info.alertsTags.map((tag, index) => (
                    <Chip
                      key={index}
                      label={tag}
                      size="small"
                      variant="outlined"
                      onClick={(event) => handleAlertTagClick(event, info.tags, info.state, tag)}
                      sx={{
                        height: 20,
                        fontSize: "0.65rem",
                        borderColor: palette.error.main,
                        color: palette.error.main,
                        fontWeight: "bold",
                        backgroundColor: alpha(palette.error.main, 0.1),
                        transition: "all 0.3s ease",
                        "&:hover": {
                          transform: "scale(1.15)"
                        }
                      }}
                    />
                  ))}
                </Box>
              </Box>
            )}
          </Collapse>
          <Box sx={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
            <Typography variant="caption" sx={{ color: palette.grey[600], fontWeight: 500 }}>
              Updated {formatTimeAgo(info.updatedAt)}
            </Typography>
            <Box
              sx={{
                width: 8,
                height: 8,
                borderRadius: "50%",
                backgroundColor:
                  info.state === "resolved"
                    ? palette.success.main
                    : info.state === "warning"
                      ? palette.warning.main
                      : palette.error.main,
                animation: info.state !== "resolved" ? `${pulse} 1s infinite ease-in-out` : "none"
              }}
            />
          </Box>
        </CardContent>
        <Menu
          anchorEl={anchorEl}
          open={open}
          onClose={handleMenuClose}
          transformOrigin={{ horizontal: "right", vertical: "top" }}
          anchorOrigin={{ horizontal: "right", vertical: "bottom" }}
          slotProps={{
            paper: {
              elevation: 8,
              sx: {
                minWidth: 140,
                borderRadius: 2,
                "& .MuiMenuItem-root": {
                  fontSize: "0.875rem",
                  "&:hover": {
                    backgroundColor: "rgba(0, 0, 0, 0.04)"
                  }
                }
              }
            }
          }}
        >
          <MenuItem onClick={handleEdit}>
            <ListItemIcon>
              <HiPencil size="1.4rem" />
            </ListItemIcon>
            <ListItemText>Edit</ListItemText>
          </MenuItem>
          <MenuItem onClick={handleDelete} sx={{ color: "error.main" }}>
            <ListItemIcon sx={{ color: "error.main" }}>
              <HiTrash size="1.4rem" />
            </ListItemIcon>
            <ListItemText>Delete</ListItemText>
          </MenuItem>
        </Menu>
      </StateCard>
    </Box>
  );
};

export default StatusMonitoringCards;
