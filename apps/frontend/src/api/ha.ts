"use server";

import type { IHaStatusResponse } from "@/@types/ha";
import axios from "@/lib/axios";

const HA_URL = "ha/cluster/status";

export async function getHaStatus(): Promise<IHaStatusResponse> {
  try {
    const response = await axios.get<IHaStatusResponse>(HA_URL);
    return response.data;
  } catch {
    return {
      enabled: false,
      cluster: null
    };
  }
}

