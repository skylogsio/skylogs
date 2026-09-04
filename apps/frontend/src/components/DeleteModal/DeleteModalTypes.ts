import type { Theme } from "@mui/material";
import type { SystemStyleObject } from "@mui/system";

import type { ModalContainerProps } from "@/components/Modal/types";

export interface DeleteModalProps
  extends Pick<ModalContainerProps, "onClose" | "open" | "children" | "paperSx"> {
  onAfterDelete?: () => void;
  isLoading?: boolean;
  contentSx?: SystemStyleObject<Theme>;
}
