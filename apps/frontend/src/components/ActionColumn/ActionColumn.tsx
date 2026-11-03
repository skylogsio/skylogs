import { type MouseEvent, type ReactNode, useState } from "react";

import { IconButton, Stack } from "@mui/material";
import { BsFillClipboard2Fill } from "react-icons/bs";
import { HiCheck, HiKey, HiPencil, HiTrash } from "react-icons/hi";

import { actionButtonSx } from "./actionButtonStyles";

export interface ActionColumnProps {
  children?: ReactNode;
  onEdit?: () => void;
  onDelete?: () => void;
  onChangePassword?: () => void;
  copyValue?: string;
}

export default function ActionColumn({
  children,
  onEdit,
  onDelete,
  onChangePassword,
  copyValue
}: ActionColumnProps) {
  const [isCopied, setIsCopied] = useState(false);

  async function handleCopyToClipboard(event: MouseEvent<HTMLButtonElement>) {
    event.stopPropagation();
    try {
      await window.navigator.clipboard.writeText(copyValue!);
      setIsCopied(true);
      setTimeout(() => {
        setIsCopied(false);
      }, 2000);
    } catch (err) {
      console.error("Unable to copy to clipboard.", err);
      alert("Copy to clipboard failed.");
    }
  }

  return (
    <Stack
      direction="row"
      spacing={0.75}
      sx={{
        justifyContent: "center",
        alignItems: "center"
      }}
      onClick={(event) => event.stopPropagation()}
    >
      {children}
      {onEdit && (
        <IconButton
          onClick={(event) => {
            event.stopPropagation();
            onEdit();
          }}
          aria-label="Edit"
          title="Edit"
          sx={actionButtonSx("primary.main")}
        >
          <HiPencil size="1.15rem" />
        </IconButton>
      )}
      {copyValue && (
        <IconButton
          aria-label={isCopied ? "Copied" : "Copy to clipboard"}
          title={isCopied ? "Copied" : "Copy to clipboard"}
          sx={actionButtonSx(isCopied ? "success.main" : "text.secondary")}
          onClick={handleCopyToClipboard}
        >
          {isCopied ? <HiCheck size="1.15rem" /> : <BsFillClipboard2Fill size="1.05rem" />}
        </IconButton>
      )}
      {onChangePassword && (
        <IconButton
          onClick={(event) => {
            event.stopPropagation();
            onChangePassword();
          }}
          aria-label="Change password"
          title="Change password"
          sx={actionButtonSx("warning.main")}
        >
          <HiKey size="1.2rem" />
        </IconButton>
      )}
      {onDelete && (
        <IconButton
          onClick={(event) => {
            event.stopPropagation();
            onDelete();
          }}
          aria-label="Delete"
          title="Delete"
          sx={actionButtonSx("error.main")}
        >
          <HiTrash size="1.2rem" />
        </IconButton>
      )}
    </Stack>
  );
}

