import { useState } from "react";

import { alpha, Chip, IconButton, Popover, Stack } from "@mui/material";
import { HiDotsHorizontal } from "react-icons/hi";

const ChipStyle = {
  maxWidth: 100,
  whiteSpace: "nowrap",
  overflow: "hidden",
  textOverflow: "ellipsis"
};

export default function TagsCell({ tags }: { tags: string[] }) {
  const [showMoreAnchorEl, setShowMoreAnchorEl] = useState<HTMLButtonElement | null>(null);

  if (!tags || tags.length === 0) {
    return;
  }

  const visibleTags = tags.slice(0, 3);
  const hiddenTags = tags.slice(3);

  const handleShowMorePopoverOpen = (event: React.MouseEvent<HTMLButtonElement>) => {
    setShowMoreAnchorEl(event.currentTarget);
  };

  const handleShowMorePopoverClose = () => {
    setShowMoreAnchorEl(null);
  };

  const open = Boolean(showMoreAnchorEl);
  const showMorePopoverId = open ? "show-more-popover" : undefined;

  return (
    <Stack direction="row" spacing={1} justifyContent="center" alignItems="flex-start">
      {visibleTags.map((tag, index) => (
        <Chip
          key={index}
          variant="filled"
          label={tag}
          sx={{
            marginBottom: ({ spacing }) => `${spacing(1)} !important`,
            ...ChipStyle
          }}
        />
      ))}
      {hiddenTags.length > 0 && (
        <>
          <IconButton
            size="small"
            sx={{ backgroundColor: ({ palette }) => alpha(palette.secondary.light, 0.2) }}
            onClick={handleShowMorePopoverOpen}
          >
            <HiDotsHorizontal size="1.3rem" />
          </IconButton>
          <Popover
            id={showMorePopoverId}
            open={open}
            anchorEl={showMoreAnchorEl}
            onClose={handleShowMorePopoverClose}
            anchorOrigin={{
              vertical: "bottom",
              horizontal: "center"
            }}
            transformOrigin={{
              vertical: "top",
              horizontal: "center"
            }}
          >
            <Stack padding={1} direction="row" gap={1} flexWrap="wrap" maxWidth={300}>
              {hiddenTags.map((tag, index) => (
                <Chip key={index} variant="filled" label={tag} sx={ChipStyle} />
              ))}
            </Stack>
          </Popover>
        </>
      )}
    </Stack>
  );
}
