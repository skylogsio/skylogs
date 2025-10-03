"use server";

import { AxiosError } from "axios";

import type { ServerResponse } from "@/@types/global";
import type { IUser } from "@/@types/user";
import axios from "@/lib/axios";

const USER_URL = "user";
const USER_PASSWORD_URL = "user/pass";

export async function getAllUsers(): Promise<IUser[]> {
  try {
    const response = await axios.get<IUser[]>(`${USER_URL}/all`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function createUser(body: unknown): Promise<ServerResponse<unknown> | undefined> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(USER_URL, body);
    return response.data;
  } catch (error) {
    const tempError = error as AxiosError<ServerResponse<unknown>>;
    return tempError.response?.data;
  }
}

export async function updateUser(userId: string, body: unknown) {
  try {
    const response = await axios.put<ServerResponse<unknown>>(`${USER_URL}/${userId}`, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteUser(userId: string) {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(`${USER_URL}/${userId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function changePassword(userId: string, body: unknown) {
  try {
    const response = await axios.put<ServerResponse<unknown>>(
      `${USER_PASSWORD_URL}/${userId}`,
      body
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}
