/* eslint-disable @typescript-eslint/no-explicit-any */
"use client";

import { useParams } from "next/navigation";
import { useState } from "react";

import {
  alpha,
  Box,
  Button,
  Grid,
  IconButton,
  Menu,
  MenuItem,
  Stack,
  Typography,
  useTheme
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { HiOutlinePlusSm, HiOutlineSearch } from "react-icons/hi";

import { getBehaviorRuleOfAlertRule } from "@/api/alertRule";

import BehaviorCard from "./BehaviorRuleCard";
import BehaviorRuleChip from "./BehaviorRuleChip";
import type { BehaviorRuleFilterType, BehaviorRuleType } from "./BehaviorRuleType";
import NotificationRuleModal from "./NotificationRule";

export default function AdvanceSection() {
  const { alertId } = useParams<{ alertId: string }>();
  const { palette } = useTheme();
  const [filter, setFilter] = useState<BehaviorRuleFilterType>("all");
  const [createMenuAnchor, setCreateMenuAnchor] = useState<null | HTMLElement>(null);
  const [selectedModal, setSelectedModal] = useState<BehaviorRuleType | null>(null);
  const [modalData, setModalData] = useState<"NEW" | never>();

  const { data } = useQuery({
    queryKey: ["get-behavior-rule", alertId],
    queryFn: () => getBehaviorRuleOfAlertRule(alertId)
  });

  function handleOpenModal(type: BehaviorRuleType) {
    setCreateMenuAnchor(null);
    setModalData("NEW");
    setSelectedModal(type);
  }

  const filters: BehaviorRuleFilterType[] = ["all", "template", "notification", "silent"];

  if (!data) return null;

  const filtered =
    filter === "all" ? data.rules : data.rules.filter((item: any) => item.type === filter);

  return (
    <>
      <Box>
        <Stack
          direction="row"
          sx={{ justifyContent: "space-between", alignItems: "flex-start", mb: 1 }}
        >
          <Box>
            <Typography variant="h5" sx={{ fontWeight: 800, fontSize: 28 }}>
              Advance
            </Typography>
            <Typography variant="body2" sx={{ color: palette.text.secondary, mt: 0.7, mb: 2 }}>
              Manage and organize your templates &amp; Notification Flows &amp; Silent Rules.
            </Typography>
          </Box>
          <Stack direction="row" spacing={1.5} sx={{ alignItems: "center" }}>
            <IconButton
              sx={{
                border: `1px solid ${alpha(palette.divider, 0.8)}`,
                borderRadius: 2,
                padding: 1,
                color: palette.text.secondary
              }}
            >
              <HiOutlineSearch size={20} />
            </IconButton>
            <Button
              variant="contained"
              startIcon={<HiOutlinePlusSm size={18} />}
              onClick={(e) => setCreateMenuAnchor(e.currentTarget)}
              sx={{
                borderRadius: 2,
                textTransform: "none",
                fontWeight: 600,
                paddingX: 2.5,
                backgroundColor: palette.primary.main
              }}
            >
              CREATE NEW ONE
            </Button>
            <Menu
              anchorEl={createMenuAnchor}
              open={Boolean(createMenuAnchor)}
              onClose={() => setCreateMenuAnchor(null)}
              anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
              transformOrigin={{ vertical: "top", horizontal: "right" }}
              slotProps={{
                paper: {
                  sx: {
                    mt: 0.5,
                    minWidth: 180,
                    borderRadius: 2,
                    border: `1px solid ${alpha(palette.divider, 0.6)}`,
                    boxShadow: `0 8px 24px ${alpha(palette.common.black, 0.12)}`
                  }
                }
              }}
            >
              <MenuItem
                onClick={() => handleOpenModal("template")}
                sx={{ color: palette.primary.main, fontWeight: 600, fontSize: 14 }}
              >
                Template
              </MenuItem>
              <MenuItem onClick={() => handleOpenModal("notification")} sx={{ fontSize: 14 }}>
                Notification Rule
              </MenuItem>
              <MenuItem onClick={() => handleOpenModal("silent")} sx={{ fontSize: 14 }}>
                Silent Rule
              </MenuItem>
            </Menu>
          </Stack>
        </Stack>

        <Stack direction="row" spacing={0.5} sx={{ mb: 3 }}>
          {filters.map((item) => (
            <BehaviorRuleChip
              key={item}
              label={item}
              clickable
              active={item === filter}
              onClick={() => setFilter(item)}
            />
          ))}
        </Stack>

        <Grid container spacing={2}>
          {filtered.map((item: any) => (
            <Grid key={item.id} size={{ xs: 12, sm: 6, lg: 4 }}>
              <BehaviorCard
                item={item}
                onEdit={() => {
                  setSelectedModal(item.type);
                  setModalData(item);
                }}
              />
            </Grid>
          ))}
        </Grid>
      </Box>
      {selectedModal === "notification" && (
        <NotificationRuleModal
          open={selectedModal === "notification"}
          onClose={() => setSelectedModal(null)}
          data={modalData!}
        />
      )}
    </>
  );
}
