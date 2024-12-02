// eslint-disable-next-line @typescript-eslint/no-unused-vars, no-unused-vars
import NextAuth, { DefaultSession, type Session, type Account } from "next-auth";

declare module "next-auth" {
  interface Session {
    token?: accessToken;
    user: {
      token?: accessToken;
    } & DefaultSession["user"];
  }
  interface Account {
    access_token: accessToken;
    user: {
      token?: accessToken;
    } & DefaultSession["user"];
  }
}
