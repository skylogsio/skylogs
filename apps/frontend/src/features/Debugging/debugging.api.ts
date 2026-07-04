"use server";

import axiosInstance from "@/lib/axios";

import { DebuggingBarType, GetDebuggingsParams } from "./debugging.type";

const ALERT_RULE_URL = "alert-rule";

export async function getDebuggingBars({
  alertRuleIds,
  fromTime,
  toTime,
  bucketCount
}: GetDebuggingsParams): Promise<DebuggingBarType[]> {
  try {
    const response = await axiosInstance.get<Array<DebuggingBarType>>(`${ALERT_RULE_URL}/status`, {
      params: {
        alertRuleIds: alertRuleIds.join(","),
        fromTime,
        toTime,
        bucketCount
      }
    });
    return response.data;
  } catch (error) {
    throw error;
  }
}
