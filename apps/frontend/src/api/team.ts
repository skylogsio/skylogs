"use server";

import type { ServerResponse } from "@/@types/global";
import type { ITeam, ITeamCreateRequest, ITeamUpdateRequest } from "@/@types/team";
import axios from "@/lib/axios";

const TEAM_URL = "team";

export async function createTeam(body: ITeamCreateRequest): Promise<ServerResponse<ITeam>> {
  try {
    const response = await axios.post<ServerResponse<ITeam>>(TEAM_URL, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function updateTeam(
  teamId: ITeam["id"],
  body: ITeamUpdateRequest
): Promise<ServerResponse<ITeam>> {
  try {
    const response = await axios.put<ServerResponse<ITeam>>(`${TEAM_URL}/${teamId}`, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteTeam(teamId: ITeam["id"]): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(`${TEAM_URL}/${teamId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}
