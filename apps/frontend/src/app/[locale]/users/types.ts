export interface CreateUserModalProps {
  open: boolean;
  onClose: () => void;
  mode?: "CREATE" | "UPDATE_PASSWORD" | "UPDATE_INFO";
}
