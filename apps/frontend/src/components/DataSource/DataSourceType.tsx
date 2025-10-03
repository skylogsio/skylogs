import { Stack, Typography, type TypographyProps } from "@mui/material";

import { DATA_SOURCE_VARIANTS, type DataSourceType } from "@/utils/dataSourceUtils";

interface IDataSourceTypeProps {
  type: DataSourceType;
  typographyProps?: TypographyProps;
  iconSize?: string | number;
  iconColor?: string;
}
export default function DataSourceType({
  type,
  typographyProps,
  iconSize,
  iconColor
}: IDataSourceTypeProps) {
  const dataSourceType = DATA_SOURCE_VARIANTS[type];

  if (!dataSourceType) return;

  return (
    <Stack direction="row" spacing={1} alignItems="center" justifyContent="center">
      <dataSourceType.Icon
        size={iconSize ?? dataSourceType.defaultSize}
        color={iconColor ?? dataSourceType.defaultColor}
      />
      <Typography component="div" textTransform="capitalize" {...typographyProps}>
        {dataSourceType?.label}
      </Typography>
    </Stack>
  );
}
