import { alpha, toggleButtonGroupClasses } from "@mui/material";
import MuiToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import { styled } from "@mui/system";

const ToggleButtonGroup = styled(MuiToggleButtonGroup)(({ theme: { palette } }) => ({
  [`&.${toggleButtonGroupClasses.root}`]: {
    border: `1px solid ${palette.grey[100]} !important`,
    borderRadius: "0.9rem",
    padding: "0.3rem",
    [`& .${toggleButtonGroupClasses.grouped}`]: {
      border: "none !important",
      padding: "0.2rem 0.6rem",
      textTransform: "none",
      borderRadius: "0.5rem",
      [`&.${toggleButtonGroupClasses.selected}`]: {
        backgroundColor: alpha(palette.primary.main, 0.11),
        color: palette.primary.main,
        [`&.${toggleButtonGroupClasses.disabled}`]: {
          color: palette.grey[600],
          backgroundColor: alpha(palette.grey[600], 0.1)
        }
      }
    }
  }
}));

export default ToggleButtonGroup;
