import { type ChangeEvent, useState } from "react";

import {
  Button,
  Checkbox,
  FormControlLabel,
  Grid2 as Grid,
  TextField,
  ToggleButton,
  Typography
} from "@mui/material";

import ModalContainer from "@/components/Modal";
import ToggleButtonGroup from "@/components/ToggleButtonGroup";

import { CreateUserModalProps } from "./types";

const BUTTON_TEXT: { [K in NonNullable<CreateUserModalProps["mode"]>]: string } = {
  CREATE: "Create",
  UPDATE_INFO: "Edit",
  UPDATE_PASSWORD: "Change Password"
};

export default function CreateUserModal({ open, onClose, mode = "CREATE" }: CreateUserModalProps) {
  const [showPassword, setShowPassword] = useState(false);

  function handleTogglePassword(event: ChangeEvent<HTMLInputElement>, checked: boolean) {
    setShowPassword(checked);
  }

  return (
    <ModalContainer title="Create New EndPoint" open={open} onClose={onClose} disableEscapeKeyDown>
      <Grid container spacing={2} width="100%" display="flex" marginTop="2rem">
        <Grid size={12} display="flex" justifyContent="flex-start" alignItems="center">
          <Typography variant="body1" component="div" marginRight="0.7rem">
            Role:
          </Typography>
          <ToggleButtonGroup value="user" disabled={mode === "UPDATE_PASSWORD"}>
            <ToggleButton value="user">User</ToggleButton>
            <ToggleButton value="admin">Admin</ToggleButton>
          </ToggleButtonGroup>
        </Grid>
        <Grid size={6}>
          <TextField label="Username" variant="filled" disabled={mode === "UPDATE_PASSWORD"} />
        </Grid>
        <Grid size={6}>
          <TextField label="Full Name" variant="filled" disabled={mode === "UPDATE_PASSWORD"} />
        </Grid>
        {mode !== "UPDATE_INFO" && (
          <>
            <Grid size={6}>
              <TextField
                label="Password"
                type={showPassword ? "text" : "password"}
                variant="filled"
              />
            </Grid>
            <Grid size={6}>
              <TextField
                label="Confirm Password"
                type={showPassword ? "text" : "password"}
                variant="filled"
              />
            </Grid>
            <Grid size={6} display="flex">
              <FormControlLabel
                sx={{ userSelect: "none" }}
                control={
                  <Checkbox
                    id="ShowPassword"
                    checked={showPassword}
                    onChange={handleTogglePassword}
                  />
                }
                label="Show Password"
              />
            </Grid>
          </>
        )}
        <Grid size={12} marginTop="1rem">
          <Button variant="contained" size="large" fullWidth>
            {BUTTON_TEXT[mode]}
          </Button>
        </Grid>
      </Grid>
    </ModalContainer>
  );
}
