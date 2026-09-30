export interface IEndpoint {
  userId: string;
  name: string;
  type: "sms" | "telegram" | "bale" | "teams" | "call" | "email" | "discord" | "matter-most";
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
  /** Only sent on an alert rule's endpoints; false when the current user may not remove it. */
  canRemove?: boolean;
}

export interface IOTPResponse {
  message: string;
  expiredAt: number;
  timeLeft: number;
}
