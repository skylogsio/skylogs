import { Box, Chip, Stack, Typography, alpha, useTheme } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { AiFillApi, AiFillClockCircle } from "react-icons/ai";

import type { IFlow, IFlowStep } from "@/@types/flow";
import { getAllEndpoints } from "@/api/flow";
import ModalContainer from "@/components/Modal";
import type { ModalContainerProps } from "@/components/Modal/types";
import { getGlassCardSx } from "@/components/Wrapper/topBarStyles";
import { useCurrentTheme } from "@/hooks";

type FlowDetailsModalProps = Pick<ModalContainerProps, "open" | "onClose"> & {
  data: IFlow;
};

function StepItem({
  step,
  index,
  total,
  endpointNames
}: {
  step: IFlowStep;
  index: number;
  total: number;
  endpointNames: Map<string, string>;
}) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  const isWait = step.type === "wait";
  const color = isWait ? palette.warning.main : palette.primary.main;
  const Icon = isWait ? AiFillClockCircle : AiFillApi;
  const label = isWait ? `Wait ${step.duration}${step.timeUnit}` : "Endpoint";

  return (
    <Stack
      direction="row"
      spacing={2}
      sx={{ alignItems: "flex-start", py: 1.5 }}
    >
      <Box
        sx={{
          width: 36,
          height: 36,
          borderRadius: "10px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          bgcolor: alpha(color, isDark ? 0.15 : 0.1),
          border: `1px solid ${alpha(color, isDark ? 0.3 : 0.2)}`,
          flexShrink: 0,
          mt: step.endpointIds?.length ? 0.15 : 0
        }}
      >
        <Icon size={18} color={color} />
      </Box>
      <Stack spacing={0.5} sx={{ flex: 1, minWidth: 0 }}>
        <Typography variant="body2" sx={{ fontWeight: 600, color: "text.primary" }}>
          {label}
        </Typography>
        {!isWait && step.endpointIds && step.endpointIds.length > 0 && (
          <Stack direction="row" spacing={0.5} sx={{ flexWrap: "wrap", gap: 0.5 }}>
            {step.endpointIds.map((id) => (
              <Chip
                key={id}
                label={endpointNames.get(id) ?? id}
                size="small"
                sx={{
                  height: 22,
                  fontSize: "0.7rem",
                  fontWeight: 600,
                  bgcolor: alpha(palette.primary.main, isDark ? 0.15 : 0.08),
                  color: palette.primary.main,
                  border: `1px solid ${alpha(palette.primary.main, isDark ? 0.25 : 0.15)}`,
                  borderRadius: "6px",
                  "& .MuiChip-label": { px: 0.75 }
                }}
              />
            ))}
          </Stack>
        )}
      </Stack>
      <Typography
        variant="caption"
        sx={{
          color: alpha(palette.text.secondary, 0.5),
          fontWeight: 600,
          flexShrink: 0,
          mt: 0.25
        }}
      >
        {index + 1}/{total}
      </Typography>
    </Stack>
  );
}

export default function FlowDetailsModal({ open, onClose, data }: FlowDetailsModalProps) {
  const theme = useTheme();
  const { palette } = theme;
  const { isDark } = useCurrentTheme();

  const { data: endpoints } = useQuery({
    queryKey: ["endpoints"],
    queryFn: () => getAllEndpoints()
  });

  const endpointNames = new Map(
    endpoints?.map((ep) => [ep.id, ep.name]) ?? []
  );

  return (
    <ModalContainer
      title={data.name}
      open={open}
      onClose={onClose}
      maxWidth={400}
      paperSx={{
        ...getGlassCardSx(theme, isDark),
        maxHeight: "80vh",
        display: "flex",
        flexDirection: "column"
      }}
    >
      <Stack
        divider={
          <Box
            sx={{
              borderBottom: `1px dashed ${alpha(palette.text.secondary, 0.15)}`
            }}
          />
        }
        sx={{
          mt: 1,
          flex: 1,
          minHeight: 0,
          overflowY: "auto",
          pr: 0.5
        }}
      >
        {data.steps.map((step, index) => (
          <StepItem
            key={index}
            step={step}
            index={index}
            total={data.steps.length}
            endpointNames={endpointNames}
          />
        ))}
      </Stack>
    </ModalContainer>
  );
}
