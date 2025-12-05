export interface IEndpoint {
  user_id: string;
  name: string;
  type: "sms" | "telegram" | "teams" | "call";
  value: string;
  hasActionAccess: boolean;
  accessTeamIds: string[];
  accessUserIds: string[];
  updated_at: Date;
  created_at: Date;
  threadId?: string;
  chatId?: string;
  id: string;
  isPublic: boolean;
}

export interface IOTPResponse {
  message: string;
  expiredAt: number;
  timeLeft: number;
}
