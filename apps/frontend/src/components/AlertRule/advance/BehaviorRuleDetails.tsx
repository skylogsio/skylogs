import { alpha, Box, Chip, Stack, Typography, useTheme } from "@mui/material";
import { format } from "date-fns";
import { HiClock, HiTag } from "react-icons/hi";

import type { BehaviorRuleItem, SilentRuleItem } from "./BehaviorRuleType";
import { BEHAVIOR_RULE_TYPE_CONFIG } from "./BehaviorRuleUtils";

const SILENT_DATE_TIME_FORMAT = "yyyy/MM/dd HH:mm";

function formatSilentTimestamp(timestamp: number): string {
  const date = new Date(timestamp);
  return Number.isNaN(date.getTime()) ? "" : format(date, SILENT_DATE_TIME_FORMAT);
}

interface ContentChipProps {
  label: string;
  color: string;
  icon?: React.ReactElement;
  wrap?: boolean;
}

export function ContentChip({ label, color, icon, wrap = false }: ContentChipProps) {
  return (
    <Chip
      icon={icon}
      label={label}
      size="small"
      sx={{
        fontSize: 11,
        fontWeight: 500,
        color,
        backgroundColor: alpha(color, 0.1),
        border: `1px solid ${alpha(color, 0.24)}`,
        ...(wrap && {
          height: "auto",
          maxWidth: "100%",
          alignItems: "flex-start",
          py: 0.5,
          "& .MuiChip-label": {
            whiteSpace: "normal",
            wordBreak: "break-word",
            lineHeight: 1.4,
            py: 0.25
          }
        }),
        "&.MuiChip-root": {
          display: "flex",
          alignItems: "center"
        },
        "& .MuiChip-icon": {
          color,
          ml: "6px",
          mr: "-2px",
          ...(wrap && { mt: 0.25 })
        }
      }}
    />
  );
}

interface ChipSectionProps {
  title: string;
  children: React.ReactNode;
}

export function ChipSection({ title, children }: ChipSectionProps) {
  const { palette } = useTheme();

  return (
    <Stack spacing={0.75}>
      <Typography
        variant="caption"
        sx={{
          color: palette.text.secondary,
          fontWeight: 600,
          letterSpacing: 0.3,
          textTransform: "uppercase",
          fontSize: 10
        }}
      >
        {title}
      </Typography>
      <Stack direction="row" sx={{ flexWrap: "wrap", gap: 0.75 }}>
        {children}
      </Stack>
    </Stack>
  );
}

function SilentRuleDetails({ item }: { item: SilentRuleItem }) {
  const { palette } = useTheme();
  const config = BEHAVIOR_RULE_TYPE_CONFIG.silent;

  const filterChips = item.filters.filter((filter) => filter.key.trim() && filter.value.trim());
  const hasDependencies = item.dependsOnAlertRules.length > 0;
  const hasTimeRange = item.startsAt !== null || item.endsAt !== null;
  const hasFilters = filterChips.length > 0;

  if (!hasDependencies && !hasTimeRange && !hasFilters) {
    return (
      <Typography variant="caption" sx={{ color: palette.text.disabled, fontStyle: "italic" }}>
        No conditions configured
      </Typography>
    );
  }

  return (
    <Stack spacing={1.25}>
      {hasDependencies && (
        <ChipSection title="Alert Rules">
          {item.dependsOnAlertRules.map((alertRule) => (
            <ContentChip key={alertRule.id} label={alertRule.name} color={config.color} />
          ))}
          <ContentChip
            label={item.triggerState.charAt(0).toUpperCase() + item.triggerState.slice(1)}
            color={item.triggerState === "resolved" ? palette.success.main : palette.error.main}
          />
        </ChipSection>
      )}

      {hasTimeRange && (
        <ChipSection title="Time Range">
          {item.startsAt !== null && (
            <ContentChip
              label={`From ${formatSilentTimestamp(item.startsAt)}`}
              color={palette.primary.main}
              icon={<HiClock size={12} />}
            />
          )}
          {item.endsAt !== null && (
            <ContentChip
              label={`To ${formatSilentTimestamp(item.endsAt)}`}
              color={palette.primary.main}
              icon={<HiClock size={12} />}
            />
          )}
        </ChipSection>
      )}

      {hasFilters && (
        <ChipSection title="Key Value">
          {filterChips.map((filter, index) => (
            <ContentChip
              key={`${filter.key}-${filter.value}-${index}`}
              label={`${filter.key}: ${filter.value}`}
              color={palette.secondary.dark}
              icon={<HiTag size={12} />}
              wrap
            />
          ))}
        </ChipSection>
      )}
    </Stack>
  );
}

interface BehaviorRuleDetailsProps {
  item: BehaviorRuleItem;
}

export default function BehaviorRuleDetails({ item }: BehaviorRuleDetailsProps) {
  const { palette } = useTheme();
  const config = BEHAVIOR_RULE_TYPE_CONFIG[item.type];

  switch (item.type) {
    case "notification": {
      const filterChips = item.filters.filter((filter) => filter.key.trim() && filter.value.trim());
      const hasEndpoints = item.endpoints.length > 0;
      const hasFilters = filterChips.length > 0;

      if (!hasEndpoints && !hasFilters) {
        return (
          <Typography variant="caption" sx={{ color: palette.text.disabled, fontStyle: "italic" }}>
            No endpoints or filters configured
          </Typography>
        );
      }

      return (
        <Stack spacing={1.25}>
          {hasEndpoints && (
            <ChipSection title="Endpoints">
              {item.endpoints.map((endpoint) => (
                <ContentChip key={endpoint.id} label={endpoint.name} color={config.color} />
              ))}
            </ChipSection>
          )}
          {hasFilters && (
            <ChipSection title="Key Value">
              {filterChips.map((filter, index) => (
                <ContentChip
                  key={`${filter.key}-${filter.value}-${index}`}
                  label={`${filter.key}: ${filter.value}`}
                  color={palette.secondary.dark}
                  icon={<HiTag size={12} />}
                  wrap
                />
              ))}
            </ChipSection>
          )}
        </Stack>
      );
    }
    case "silent":
      return <SilentRuleDetails item={item} />;
    case "template":
      return (
        <Stack spacing={1.25}>
          {item.endpoints.length > 0 && (
            <ChipSection title="Endpoints">
              {item.endpoints.map((endpoint) => (
                <ContentChip key={endpoint.id} label={endpoint.name} color={config.color} />
              ))}
            </ChipSection>
          )}
          <Box
            sx={{
              p: 1,
              borderRadius: "0.4rem",
              backgroundColor: alpha(palette.common.black, palette.mode === "dark" ? 0.18 : 0.03),
              border: `1px solid ${palette.divider}`
            }}
          >
            <Typography
              variant="caption"
              component="pre"
              sx={{
                m: 0,
                fontFamily: "monospace",
                fontSize: 11,
                lineHeight: 1.5,
                color: palette.text.secondary,
                wordBreak: "break-word",
                whiteSpace: "pre-wrap"
              }}
            >
              {item.template || "No template configured"}
            </Typography>
          </Box>
        </Stack>
      );
    default:
      return null;
  }
}
