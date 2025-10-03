import axios, { isAxiosError } from "axios";
import { type NextAuthOptions } from "next-auth";
import CredentialsProvider from "next-auth/providers/credentials";

async function handleRefreshToken(refreshToken: string) {
  return await axios.post(`${process.env.NEXT_PUBLIC_BASE_URL}auth/refresh`, null, {
    headers: {
      Authorization: `Bearer ${refreshToken}`
    }
  });
}

export const authOptions: NextAuthOptions = {
  providers: [
    CredentialsProvider({
      name: "Credentials",
      credentials: {
        username: { label: "Username", type: "text" },
        password: { label: "Password", type: "password" }
      },
      async authorize(credentials) {
        const body = {
          username: credentials?.username,
          password: credentials?.password
        };
        try {
          const user = await axios.post(`${process.env.NEXT_PUBLIC_BASE_URL}auth/login`, body, {
            headers: { "Content-Type": "application/json" }
          });
          return user.data;
        } catch (error) {
          if (isAxiosError(error)) {
            if (error.response) {
              throw new Error(error.response.data.message);
            }
          }
        }
      }
    })
  ],
  session: { strategy: "jwt" },
  callbacks: {
    async session({ session, token }) {
      session.user.token = token.access_token;
      session.error = token.error;
      return session;
    },
    async jwt({ token, user }) {
      if (user) {
        token.id = user.id;
        token.access_token = user.access_token;
        token.refresh_token = user.refresh_token;
        token.expires_at = Date.now() + user.expires_at * 1000;
        return token;
      } else if (Date.now() < token.expires_at) {
        return token;
      } else {
        if (!token.refresh_token) throw new TypeError("Missing refresh_token");

        try {
          const response = await handleRefreshToken(token.refresh_token);
          const newTokens = response.data as {
            access_token: string;
            expires_in: number;
            refresh_token?: string;
          };

          return {
            ...token,
            access_token: newTokens.access_token,
            expires_at: Math.floor(Date.now() + newTokens.expires_in * 1000),
            refresh_token: newTokens.refresh_token ? newTokens.refresh_token : token.refresh_token
          };
        } catch (error) {
          console.error("Error refreshing access_token", error);
          token.error = "RefreshTokenError";
          return token;
        }
      }
    }
  },
  //! Attention: It should be the same as pages in middleware file
  pages: {
    signIn: "/next-auth/signIn"
  }
};
