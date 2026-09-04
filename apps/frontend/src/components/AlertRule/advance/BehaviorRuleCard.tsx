import { useState } from "react";

import {
  alpha,
  Box,
  Button,
  IconButton,
  Menu,
  MenuItem,
  Stack,
  Tooltip,
  Typography,
  useTheme
} from "@mui/material";
import { useQueryClient } from "@tanstack/react-query";
import { HiDotsVertical, HiPencil, HiTrash } from "react-icons/hi";

import BehaviorRuleChip from "./BehaviorRuleChip";
import BehaviorRuleDetails from "./BehaviorRuleDetails";
import type { BehaviorRuleItem } from "./BehaviorRuleType";
import { BEHAVIOR_RULE_TYPE_CONFIG } from "./BehaviorRuleUtils";
import DeleteBehaviorRuleModal from "./DeleteBehaviorRuleModal";

interface BehaviorRuleCardProps {
  item: BehaviorRuleItem;
  onEdit: () => void;
}

export default function BehaviorRuleCard({ item, onEdit }: BehaviorRuleCardProps) {
  const queryClient = useQueryClient();
  const { palette } = useTheme();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [deleteModalData, setDeleteModalData] = useState<BehaviorRuleItem | null>(null);
  const config = BEHAVIOR_RULE_TYPE_CONFIG[item.type];

  function handleEdit() {
    onEdit();
    setAnchorEl(null);
  }

  function handleAfterDelete() {
    setDeleteModalData(null);
    queryClient.invalidateQueries({ queryKey: ["get-behavior-rule"] });
  }

  return (
    <>
      <Box
        sx={{
          backgroundColor: palette.background.paper,
          borderRadius: "0.5rem",
          padding: 2.5,
          height: 1,
          display: "flex",
          flexDirection: "column",
          justifyContent: "space-between",
          border: 1,
          borderColor: palette.divider,
          transition: "all 0.2s ease",
          "&:hover": {
            boxShadow: `0 4px 16px ${alpha(palette.common.black, 0.08)}`,
            transform: "translateY(-2px)"
          }
        }}
      >
        <Stack
          direction="row"
          sx={{ justifyContent: "space-between", alignItems: "flex-start", mb: 1.5 }}
        >
          <Stack direction="row" spacing={1.5} sx={{ alignItems: "center", minWidth: 0, flex: 1 }}>
            <Box
              sx={{
                width: 44,
                height: 44,
                borderRadius: "0.5rem",
                backgroundColor: config.bgColor,
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                color: config.color,
                flexShrink: 0
              }}
            >
              {config.icon}
            </Box>
            <Box sx={{ minWidth: 0 }}>
              <Typography
                variant="subtitle1"
                sx={{
                  fontWeight: 700,
                  lineHeight: 1.2,
                  overflow: "hidden",
                  textOverflow: "ellipsis",
                  whiteSpace: "nowrap"
                }}
              >
                {item.name}
              </Typography>
              <BehaviorRuleChip label={item.type} showNotification showTemplate size="small" />
            </Box>
          </Stack>
          <IconButton
            size="small"
            onClick={(e) => setAnchorEl(e.currentTarget)}
            sx={{ color: palette.text.secondary, mt: -0.5, ml: 0.5 }}
          >
            <HiDotsVertical size={18} />
          </IconButton>
          <Menu
            anchorEl={anchorEl}
            open={Boolean(anchorEl)}
            onClose={() => setAnchorEl(null)}
            anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
            transformOrigin={{ vertical: "top", horizontal: "right" }}
          >
            <MenuItem onClick={handleEdit}>Edit</MenuItem>
            <MenuItem
              onClick={() => {
                setAnchorEl(null);
                setDeleteModalData(item);
              }}
              sx={{ color: "error.main" }}
            >
              Delete
            </MenuItem>
          </Menu>
        </Stack>

        <Box
          sx={{
            backgroundColor: palette.background.default,
            borderRadius: "0.4rem",
            minHeight: 88,
            maxHeight: 140,
            p: 1.25,
            mb: 1.5,
            flex: 1,
            overflowY: "auto"
          }}
        >
          <BehaviorRuleDetails item={item} />
        </Box>

        <Stack direction="row" spacing={1}>
          <Button
            size="small"
            variant="outlined"
            onClick={handleEdit}
            startIcon={<HiPencil />}
            sx={{
              flex: 1,
              borderColor: palette.divider,
              color: palette.text.secondary,
              fontSize: 12,
              paddingY: 1,
              textTransform: "none",
              "&:hover": { borderColor: config.color, color: config.color }
            }}
          >
            Edit
          </Button>
          <Tooltip title="Delete">
            <IconButton
              size="small"
              onClick={() => setDeleteModalData(item)}
              sx={{
                border: 1,
                borderColor: alpha(palette.error.main, 0.35),
                color: "error.main",
                "&:hover": {
                  borderColor: "error.main",
                  backgroundColor: alpha(palette.error.main, 0.06)
                }
              }}
            >
              <HiTrash size={16} />
            </IconButton>
          </Tooltip>
        </Stack>
      </Box>
      {deleteModalData && (
        <DeleteBehaviorRuleModal
          open={!!deleteModalData}
          onClose={() => setDeleteModalData(null)}
          onAfterDelete={handleAfterDelete}
          data={deleteModalData}
        />
      )}
    </>
  );
}
