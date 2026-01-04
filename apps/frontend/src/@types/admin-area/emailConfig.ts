export interface IEmailConfig {
  id: string;
  name: string;
  provider: string;
  usrename: string;
  password: string;
  host: string;
  port: number;
  fromAddress: string;
  isDefault: boolean;
  isBackUp: boolean;
  updatedAt: string;
  createdAt: string;
}
