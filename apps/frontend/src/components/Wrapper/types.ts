import { type InstalledLocalesType } from "@/locales/client";
import { RoleType } from "@/utils/userUtils";

export type LocalesListType = { locale: InstalledLocalesType; title: string; iconSRC: string };

export type URLType = {
  pathname: string;
  label: string;
  role?: RoleType | RoleType[];
  icon: React.ComponentType<{ size?: number | string; className?: string }>;
};
