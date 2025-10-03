//TODO: Should check with exist roles in server
export const ROLE_TYPES = ["member", "manager", "owner"] as const;

export type RoleType = (typeof ROLE_TYPES)[number];

export const ROLE_COLORS: Record<RoleType, string> = {
  member: "#4880FF",
  manager: "#B65DFE",
  owner: "#13C82B"
};
