import { PropsWithChildren, type ReactNode } from "react";

import type { ModalProps } from "@mui/material";

export interface ModalContainerProps extends PropsWithChildren {
  open: ModalProps["open"];
  title?: string | ReactNode;
  width?: string | number;
  maxWidth?: string | number;
  padding?: string | number;
  disableAccidentalClose?: boolean;
  disableEscapeKeyDown?: boolean;
  onClose?: () => void;
}
