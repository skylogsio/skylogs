// eslint-disable-next-line @typescript-eslint/no-unused-vars, no-unused-vars
import NextAuth, { DefaultSession, type Session, type Account } from "next-auth";

declare module "next-auth" {
  interface Session {
    token?: string;
    user: {
      token?: string;
    } & DefaultSession["user"];
    error?: "RefreshTokenError";
  }
  interface User {
    access_token: string;
    expires_at: number;
    refresh_token?: string;
  }
  interface Account {
    access_token: string;
    user: {
      token?: string;
    } & DefaultSession["user"];
  }
}

declare module "next-auth/jwt" {
  interface JWT {
    access_token: string;
    expires_at: number;
    refresh_token?: string;
    error?: "RefreshTokenError";
  }
}
