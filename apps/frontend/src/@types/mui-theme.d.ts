import "@mui/material/styles";

declare module "@mui/material/styles" {
  interface Palette {
    endpoint: {
      sms: string;
      telegram: string;
      teams: string;
      call: string;
      email: string;
      flow: string;
      discord: string;
      "matter-most": string;
    };
  }

  interface PaletteOptions {
    endpoint?: {
      sms?: string;
      telegram?: string;
      teams?: string;
      call?: string;
      email?: string;
      flow?: string;
      discord?: string;
      "matter-most"?: string;
    };
  }
}
