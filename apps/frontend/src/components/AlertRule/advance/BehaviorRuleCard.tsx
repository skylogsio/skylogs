import { useState } from "react";

import {
  alpha,
  Box,
  Button,
  Chip,
  IconButton,
  Menu,
  MenuItem,
  Stack,
  Typography,
  useTheme
} from "@mui/material";
import { useQueryClient } from "@tanstack/react-query";
import { HiDotsVertical, HiPencil, HiTrash, HiOutlineDuplicate } from "react-icons/hi";

import BehaviorRuleChip from "./BehaviorRuleChip";
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

  function renderChip() {
    switch (item.type) {
      case "notification":
        return item.endpoints.map((endpoint) => (
          <Chip key={endpoint.id} label={endpoint.name} variant="filled" size="small" />
        ));
      case "silent":
        return item.dependsOnAlertRules.map((alertRule) => (
          <Chip key={alertRule.id} label={alertRule.name} variant="filled" size="small" />
        ));
      case "template":
        return (
          <Typography variant="caption" component="pre">
            {item.template}
          </Typography>
        );
      default:
        break;
    }
  }

  return (
    <>
      <Box
        sx={{
          backgroundColor: palette.background.paper,
          borderRadius: 3,
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
          <Stack direction="row" spacing={1.5} sx={{ alignItems: "center" }}>
            <Box
              sx={{
                width: 44,
                height: 44,
                borderRadius: 2,
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
            <Box>
              <Typography variant="subtitle1" sx={{ fontWeight: 700, lineHeight: 1.2 }}>
                {item.name}
              </Typography>
              <BehaviorRuleChip label={item.type} showNotification showTemplate size="small" />
            </Box>
          </Stack>
          <IconButton
            size="small"
            onClick={(e) => setAnchorEl(e.currentTarget)}
            sx={{ color: palette.text.secondary, mt: -0.5 }}
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
            <MenuItem onClick={() => setAnchorEl(null)}>Duplicate</MenuItem>
            <MenuItem onClick={() => setAnchorEl(null)} sx={{ color: "error.main" }}>
              Delete
            </MenuItem>
          </Menu>
        </Stack>

        <Stack
          direction="row"
          sx={{
            backgroundColor: palette.background.default,
            borderRadius: 1.5,
            p: 0.75,
            mb: 1.5,
            flex: 1
          }}
          spacing={0.5}
        >
          {renderChip()}
        </Stack>

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
              borderRadius: 1.5,
              "&:hover": { borderColor: palette.text.secondary }
            }}
          >
            Edit
          </Button>
          <Button
            size="small"
            variant="outlined"
            startIcon={<HiOutlineDuplicate />}
            sx={{
              flex: 1,
              borderColor: palette.divider,
              color: palette.text.secondary,
              fontSize: 12,
              paddingY: 1,
              textTransform: "none",
              borderRadius: 1.5,
              "&:hover": { borderColor: palette.text.secondary }
            }}
          >
            Duplicate
          </Button>
          <Button
            size="small"
            variant="outlined"
            startIcon={<HiTrash />}
            onClick={() => setDeleteModalData(item)}
            sx={{
              flex: 1,
              borderColor: alpha(palette.error.light, 0.4),
              color: "error.main",
              paddingY: 1,
              fontSize: 12,
              textTransform: "none",
              borderRadius: 1.5,
              "&:hover": {
                borderColor: "error.main",
                backgroundColor: alpha(palette.error.light, 0.04)
              }
            }}
          >
            Delete
          </Button>
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
