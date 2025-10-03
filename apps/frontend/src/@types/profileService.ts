import type { IUser } from "@/@types/user";

export interface IProfileService {
  name: string;
  userId: string;
  ownerId: string;
  config: string;
  updatedAt: string;
  createdAt: string;
  createdAlertRuleIds: Array<string>;
  id: string;
  envs: Array<string>;
  user: IUser;
}
