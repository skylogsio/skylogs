import type { IUser } from "@/@types/user";

export interface ITeam {
  id: string;
  name: string;
  ownerId: string;
  userIds: string[];
  owner: IUser;
  members: string[];
  createdAt: string;
  updatedAt: string;
  description?: string;
}

export interface ITeamCreateRequest {
  name: string;
  ownerId: string;
  userIds: string[];
}

export interface ITeamUpdateRequest {
  name: string;
  ownerId: string;
  userIds: string[];
}
