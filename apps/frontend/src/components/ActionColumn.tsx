import { type ReactNode, useState } from "react";

import { alpha, IconButton, Stack } from "@mui/material";
import { BsFillClipboard2Fill } from "react-icons/bs";
import { HiCheck, HiKey, HiPencil, HiTrash } from "react-icons/hi";

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

  async function handleCopyToClipboard() {
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
    <>
      <Stack direction="row" spacing={1} justifyContent="center">
        {children}
        {onEdit && (
          <IconButton
            onClick={onEdit}
            sx={({ palette }) => ({
              color: palette.info.light,
              backgroundColor: alpha(palette.info.light, 0.05)
            })}
          >
            <HiPencil size="1.4rem" />
          </IconButton>
        )}
        {copyValue && (
          <IconButton
            sx={({ palette }) => ({
              color: palette.secondary.main,
              backgroundColor: alpha(palette.secondary.dark, 0.05),
              padding: 1.4,
              paddingX: 1.25
            })}
            onClick={() => handleCopyToClipboard()}
          >
            {isCopied ? <HiCheck size="1.1rem" /> : <BsFillClipboard2Fill size="1.1rem" />}
          </IconButton>
        )}
        {onChangePassword && (
          <IconButton
            onClick={onChangePassword}
            sx={({ palette }) => ({
              color: palette.secondary.main,
              backgroundColor: alpha(palette.secondary.dark, 0.05)
            })}
          >
            <HiKey size="1.3rem" />
          </IconButton>
        )}
        {onDelete && (
          <IconButton
            onClick={onDelete}
            sx={({ palette }) => ({
              color: palette.error.light,
              backgroundColor: alpha(palette.error.light, 0.05)
            })}
          >
            <HiTrash size="1.4rem" />
          </IconButton>
        )}
      </Stack>
    </>
  );
}
