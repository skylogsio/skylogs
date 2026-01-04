export interface ISmsConfig {
  id: string;
  name: string;
  provider: string;
  apiToken: string;
  senderNumber: string;
  isDefault: boolean;
  isBackUp: boolean;
  updatedAt: string;
  createdAt: string;
}
