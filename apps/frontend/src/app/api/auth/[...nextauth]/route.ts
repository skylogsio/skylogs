import NextAuth from "next-auth";
import CredentialsProvider from "next-auth/providers/credentials";

const handler = NextAuth({
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
        const res = await fetch(`${process.env.NEXT_BASE_URL}auth/login`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(body)
        });

        const user = await res.json();
        if (res.ok && user) {
          return user;
        }
        throw new Error(JSON.stringify(user));
      }
    })
  ],
  secret: process.env.NEXTAUTH_SECRET,
  session: { strategy: "jwt" },
  callbacks: {
    async jwt({ token, user }) {
      //TODO: We should add logic for refreshing the access token after it expired
      return { ...token, ...user };
    },
    async session({ session, token }) {
      session.user = token as never;
      return session;
    }
  },
  //! Attention: It should be the same as pages in middleware file
  pages: {
    signIn: "/auth/signIn"
  }
});

export { handler as GET, handler as POST };
