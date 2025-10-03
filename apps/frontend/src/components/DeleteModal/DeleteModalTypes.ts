import type { ModalContainerProps } from "@/components/Modal/types";

export interface DeleteModalProps
  extends Pick<ModalContainerProps, "onClose" | "open" | "children"> {
  onAfterDelete?: () => void;
  isLoading?: boolean;
}
