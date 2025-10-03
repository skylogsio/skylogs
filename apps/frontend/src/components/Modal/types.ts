import { PropsWithChildren, type ReactNode } from "react";

import type { BoxProps, ModalProps } from "@mui/material";

export interface ModalContainerProps
  extends PropsWithChildren,
    Pick<ModalProps, "open" | "disableEscapeKeyDown">,
    Pick<BoxProps, "width" | "maxWidth" | "padding"> {
  title?: string | ReactNode;
  disableAccidentalClose?: boolean;
  onClose?: () => void;
}
