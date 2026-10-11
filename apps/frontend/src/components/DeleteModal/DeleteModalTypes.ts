import type { SxProps } from "@mui/material";

import type { ModalContainerProps } from "@/components/Modal/types";

export interface DeleteModalProps
  extends Pick<ModalContainerProps, "onClose" | "open" | "children" | "paperSx"> {
  onAfterDelete?: () => void;
  isLoading?: boolean;
  contentSx?: SxProps;
}
