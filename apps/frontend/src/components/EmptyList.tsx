import type { ReactNode } from "react";

import { Box, Typography, alpha } from "@mui/material";
import type { SxProps, Theme } from "@mui/material/styles";

interface EmptyListProps {
  icon?: ReactNode;
  title: string;
  description?: string;
  sx?: SxProps<Theme>;
  children?: ReactNode;
}

export default function EmptyList({ icon, title, description, sx = {}, children }: EmptyListProps) {
  return (
    <Box
      sx={{
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        py: 6,
        px: 2,
        textAlign: "center",
        backgroundColor: (theme) => alpha(theme.palette.grey[100], 0.5),
        borderRadius: 2,
        ...sx
      }}
    >
      {icon && <Box sx={{ mb: 2 }}>{icon}</Box>}
      <Typography variant="h6" color="text.secondary" textTransform="capitalize" gutterBottom>
        {title}
      </Typography>
      {description && (
        <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 400 }}>
          {description}
        </Typography>
      )}
      {children}
    </Box>
  );
}
