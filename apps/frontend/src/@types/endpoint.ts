export interface IEndpoint {
  user_id: string;
  name: string;
  type: "sms" | "telegram" | "teams" | "call";
  value: string;
  updated_at: Date;
  created_at: Date;
  threadId?: string;
  chatId?: string;
  id: string;
  isPublic: boolean;
}
