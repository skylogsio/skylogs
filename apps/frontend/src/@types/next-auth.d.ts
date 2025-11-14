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
    accessToken: string;
    expiresIn: number;
    refreshToken?: string;
  }
  interface Account {
    accessToken: string;
    user: {
      token?: string;
    } & DefaultSession["user"];
  }
}

declare module "next-auth/jwt" {
  interface JWT {
    accessToken: string;
    expiresIn: number;
    refreshToken?: string;
    error?: "RefreshTokenError";
  }
}
