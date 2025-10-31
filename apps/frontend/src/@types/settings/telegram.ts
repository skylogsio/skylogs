export interface ITelegramProxy {
  id: string;
  name: string;
  active: boolean;
  updatedAt: string;
  createdAt: string;
  type: "http" | "socks5";
  host: string;
  port: number;
  username: string;
  password: string;
}
