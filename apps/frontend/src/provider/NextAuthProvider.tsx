"use client";

import { PropsWithChildren } from "react";

import { SessionProvider } from "next-auth/react";

export default function NextAuthProvider({ children }: PropsWithChildren<object>) {
  return <SessionProvider>{children}</SessionProvider>;
}
