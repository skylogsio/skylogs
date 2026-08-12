export const ROLE_TYPES = ["member", "manager", "owner"] as const;

export type RoleType = (typeof ROLE_TYPES)[number];

export const ROLE_COLORS: Record<RoleType, string> = {
  member: "#A08B74",
  manager: "#E08A3A",
  owner: "#3FA85A"
};
