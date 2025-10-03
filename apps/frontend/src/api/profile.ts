"use server";

import type { IUser } from "@/@types/user";
import axios from "@/lib/axios";

export async function getMyInfo(): Promise<IUser> {
  try {
    const response = await axios.post<IUser>("auth/me");
    return response.data;
  } catch (error) {
    throw error;
  }
}
