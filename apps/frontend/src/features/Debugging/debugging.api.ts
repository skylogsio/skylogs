"use server";

import axiosInstance from "@/lib/axios";

import { AlertRuleOption, DebuggingBarType, GetDebuggingsParams } from "./debugging.type";

const ALERT_RULE_URL = "alert-rule";

export async function getAllAlertRules(): Promise<AlertRuleOption[]> {
  try {
    const response = await axiosInstance.get<AlertRuleOption[]>(`${ALERT_RULE_URL}/all`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

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
