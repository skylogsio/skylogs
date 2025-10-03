import type { RoleType } from "@/utils/userUtils";

export interface IUser {
  username: string;
  name: string;
  updated_at: Date;
  created_at: Date;
  roles: RoleType[];
  id: string;
}
