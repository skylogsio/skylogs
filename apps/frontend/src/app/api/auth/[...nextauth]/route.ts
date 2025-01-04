import axios from "axios";
import NextAuth, { type User } from "next-auth";
import CredentialsProvider from "next-auth/providers/credentials";

import { getAcceptLanguage } from "@/locales/server";

const handler = NextAuth({
  providers: [
    CredentialsProvider({
      name: "Credentials",
      credentials: {
        username: { label: "Username", type: "text" },
        password: { label: "Password", type: "password" }
      },
      async authorize(credentials) {
        const acceptLanguage = await getAcceptLanguage();
        const body = {
          username: credentials?.username,
          password: credentials?.password
        };
        const user = await axios.post(`${process.env.NEXT_PUBLIC_BASE_URL}auth/login`, body, {
          headers: { "Content-Type": "application/json", "Accept-Language": acceptLanguage }
        });
        return user.data;
        // const res = await fetch(`${process.env.NEXT_PUBLIC_BASE_URL}auth/login`, {
        //   method: "POST",
        //   headers: { "Content-Type": "application/json", "Accept-Language": acceptLanguage },
        //   body: JSON.stringify(body)
        // });

        // const user = await res.json();
        // if (res.ok && user) {
        //   return user;
        // }
        // throw new Error(JSON.stringify(user));
      }
    })
  ],
  session: { strategy: "jwt" },
  callbacks: {
    async session({ session, token }) {
      session.user.token = token.accessToken;
      return session;
    },
    async jwt({ token, user }) {
      if (user) {
        token.id = user.id;
      }
      if (user) {
        token.accessToken = (user as User & { access_token: string }).access_token;
      }
      return token;
    }
  },
  //! Attention: It should be the same as pages in middleware file
  pages: {
    signIn: "/auth/signIn"
  }
});

export { handler as GET, handler as POST };
