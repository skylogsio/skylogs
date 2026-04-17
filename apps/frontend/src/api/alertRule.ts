"use server";

import type {
  IAlertRule,
  IAlertRuleCreateData,
  IAlertRuleEndpoints,
  IAlertRuleAccess,
  IZabbixCreateData
} from "@/@types/alertRule";
import type { IEndpoint } from "@/@types/endpoint";
import type {
  IServerResponseTabularData,
  ServerResponse,
  ServerSelectableDataType
} from "@/@types/global";
import type { ITeam } from "@/@types/team";
import type { IUser } from "@/@types/user";
import axios from "@/lib/axios";
import { DataSourceType } from "@/utils/dataSourceUtils";

const ALERT_RULE_URL = "alert-rule";
const ALERT_RULE_NOTIFY_URL = "alert-rule-notify";
const ALERT_RULE_USER_URL = "alert-rule-user";
const ALERT_RULE_TAGS_URL = "alert-rule-tag";
const ALERT_RULE_CREATE_DATA_URL = `${ALERT_RULE_URL}/create-data`;
const ZABBIX_CREATE_DATA_URL = `${ALERT_RULE_URL}/create-data/zabbix`;
const ALERT_RULE_GROUP_ACTION = `${ALERT_RULE_URL}/group-action`;

export async function createAlertRule(body: unknown): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(ALERT_RULE_URL, body);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function updateAlertRule(
  alertRuleId: IAlertRule["id"],
  body: unknown
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(
      `${ALERT_RULE_URL}/${alertRuleId}`,
      body
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAlertRuleById(alertId: IAlertRule["id"]) {
  try {
    const response = await axios.get<IAlertRule>(`${ALERT_RULE_URL}/${alertId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function deleteAlertRule(
  alertRuleId: IAlertRule["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(
      `${ALERT_RULE_URL}/${alertRuleId}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function testAlertRule(id: IAlertRule["id"]): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(
      `${ALERT_RULE_NOTIFY_URL}/test/${id}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function silenceAlertRule(id: IAlertRule["id"]): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(`${ALERT_RULE_URL}/silent/${id}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function pinAlertRule(id: IAlertRule["id"]): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(`${ALERT_RULE_URL}/pin/${id}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAlertFilterEndpointList(): Promise<IEndpoint[]> {
  try {
    const response = await axios.get<Array<IEndpoint>>(`${ALERT_RULE_URL}/filter-endpoints`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function resolveFiredAlertRule(
  alertRuleId: IAlertRule["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(
      `${ALERT_RULE_URL}/resolve/${alertRuleId}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function acknowledgeFiredAlertRule(
  alertRuleId: IAlertRule["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.post<ServerResponse<unknown>>(
      `${ALERT_RULE_URL}/acknowledge/${alertRuleId}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAlertRuleEndpointsList(
  alertRuleId: IAlertRule["id"]
): Promise<IAlertRuleEndpoints> {
  try {
    const response = await axios.get<IAlertRuleEndpoints>(
      `${ALERT_RULE_NOTIFY_URL}/${alertRuleId}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function addEndpointToAlertRule(
  alertRuleId: IAlertRule["id"],
  endpointIds: string[]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(
      `${ALERT_RULE_NOTIFY_URL}/${alertRuleId}`,
      { endpointIds }
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function removeEndpointFromAlertRule(
  alertRuleId: IAlertRule["id"],
  endpointId: string
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(
      `${ALERT_RULE_NOTIFY_URL}/${alertRuleId}/${endpointId}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAlertRuleAccessList(
  alertRuleId: IAlertRule["id"]
): Promise<IAlertRuleAccess> {
  try {
    const response = await axios.get<IAlertRuleAccess>(`${ALERT_RULE_USER_URL}/${alertRuleId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function addAccessToAlertRule(
  alertRuleId: IAlertRule["id"],
  body: {
    userIds: Array<IUser["id"]>;
    teamIds: Array<ITeam["id"]>;
  }
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.put<ServerResponse<unknown>>(
      `${ALERT_RULE_USER_URL}/${alertRuleId}`,
      body
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function removeAccessFromAlertRule(
  alertRuleId: IAlertRule["id"],
  accessId: IUser["id"] | ITeam["id"]
): Promise<ServerResponse<unknown>> {
  try {
    const response = await axios.delete<ServerResponse<unknown>>(
      `${ALERT_RULE_USER_URL}/${alertRuleId}/${accessId}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAlertRuleTags(): Promise<string[]> {
  try {
    const response = await axios.get<string[]>(`${ALERT_RULE_TAGS_URL}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAlertRuleLabels(): Promise<string[]> {
  try {
    const response = await axios.get<string[]>(`${ALERT_RULE_URL}/labels`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAlertRuleLabelValues(label: string): Promise<string[]> {
  try {
    const response = await axios.get<string[]>(`${ALERT_RULE_URL}/label-values/${label}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAlertRuleCreateData(): Promise<IAlertRuleCreateData> {
  try {
    const response = await axios.get<IAlertRuleCreateData>(ALERT_RULE_CREATE_DATA_URL);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getZabbixCreateData(): Promise<IZabbixCreateData> {
  try {
    const response = await axios.get<IZabbixCreateData>(ZABBIX_CREATE_DATA_URL);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAlertRuleDataSourcesByAlertType(
  type: DataSourceType
): Promise<ServerSelectableDataType> {
  try {
    const response = await axios.get<ServerSelectableDataType>(
      `${ALERT_RULE_CREATE_DATA_URL}/data-source/${type}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getDataSourceAlertName(type: DataSourceType) {
  try {
    const response = await axios.get(`${ALERT_RULE_CREATE_DATA_URL}/rules?type=${type}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getAlertRuleHistory<T>(
  alertRuleId: IAlertRule["id"],
  page: number
): Promise<IServerResponseTabularData<T>> {
  try {
    const response = await axios.get(
      `${ALERT_RULE_URL}/history/${alertRuleId}?perPage=10&page=${page}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function getFiredInstances(alertRuleId: IAlertRule["id"]) {
  try {
    const response = await axios.get(`${ALERT_RULE_URL}/triggered/${alertRuleId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function silentAlertRules(filter: object): Promise<ServerResponse<unknown>> {
  const searchParams = new URLSearchParams(filter as Record<string, string>);
  const urlSearchParams = searchParams.toString();
  try {
    const response = await axios.post<ServerResponse<unknown>>(
      `${ALERT_RULE_GROUP_ACTION}/silent?${urlSearchParams}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function unsilentAlertRules(filter: object): Promise<ServerResponse<unknown>> {
  const searchParams = new URLSearchParams(filter as Record<string, string>);
  const urlSearchParams = searchParams.toString();
  try {
    const response = await axios.post<ServerResponse<unknown>>(
      `${ALERT_RULE_GROUP_ACTION}/unsilent?${urlSearchParams}`
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}

export async function addUserAndNotifyToAlertRules(
  filter: object,
  body: unknown
): Promise<ServerResponse<unknown>> {
  const searchParams = new URLSearchParams(filter as Record<string, string>);
  const urlSearchParams = searchParams.toString();
  try {
    const response = await axios.post<ServerResponse<unknown>>(
      `${ALERT_RULE_GROUP_ACTION}/add-user-notify?${urlSearchParams}`,
      body
    );
    return response.data;
  } catch (error) {
    throw error;
  }
}
