import { NextRequest, NextResponse } from "next/server";

import { getToken } from "next-auth/jwt";
import { withAuth } from "next-auth/middleware";
import { createI18nMiddleware } from "next-international/middleware";

/*
  info:For better setup of next-international read the following links
  @link:https://next-international.vercel.app/docs/app-setup
  @link:https://next-international.vercel.app/docs/app-middleware-configuration#rewrite-the-url-to-hide-the-locale
*/
const I18nMiddleware = createI18nMiddleware({
  locales: ["en", "fa"],
  defaultLocale: "en",
  urlMappingStrategy: "rewrite"
});

const authMiddleware = withAuth(
  async function onSuccess(request: NextRequest) {
    return I18nMiddleware(request);
  },
  {
    callbacks: {
      authorized: ({ token }) => !!token
    },
    //! Attention: It should be the same as pages in next-auth route handler
    pages: {
      signIn: "/auth/signIn"
    }
  }
);

export default async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const token = await getToken({ req: request });
  const isAuthenticated = !!token;

  if (pathname.includes("signIn") && isAuthenticated) {
    return NextResponse.redirect(new URL("/", request.url));
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  return pathname.includes("/auth") ? I18nMiddleware(request) : (authMiddleware as any)(request);
}

export const config = {
  matcher: ["/((?!api|static|.*\\..*|_next|favicon.ico|robots.txt).*)"]
};
