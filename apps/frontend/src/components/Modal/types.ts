import { PropsWithChildren, type ReactNode } from "react";

import type { ModalProps, Theme } from "@mui/material";
import type { SystemStyleObject } from "@mui/system";

export interface ModalContainerProps extends PropsWithChildren {
  open: ModalProps["open"];
  title?: string | ReactNode;
  width?: string | number;
  maxWidth?: string | number;
  padding?: string | number;
  disableAccidentalClose?: boolean;
  disableEscapeKeyDown?: boolean;
  onClose?: () => void;
  paperSx?: SystemStyleObject<Theme>;
}
