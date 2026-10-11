import { type PropsWithChildren } from "react";

import { SideBarProvider } from "@/context/SideBarContext";
import { ZoneProvider } from "@/context/ZoneContext";
import { I18nProviderClient } from "@/locales/client";

import MuiProvider from "./MuiProvider";
import NextAuthProvider from "./NextAuthProvider";
import ReactQueryProvider from "./ReactQueryProvider";
import RTLProvider from "./RTLProvider";
import type { ThemeInitialMode } from "./themeStorage";

export default function Provider({
  children,
  locale,
  sidebarCollapsed = false,
  initialMode = "system"
}: PropsWithChildren<{ locale: string; sidebarCollapsed?: boolean; initialMode?: ThemeInitialMode }>) {
  return (
    <I18nProviderClient locale={locale}>
      <NextAuthProvider>
        <ReactQueryProvider>
          <RTLProvider locale={locale}>
            <MuiProvider defaultMode={initialMode}>
              <ZoneProvider>
                <SideBarProvider initialCollapsed={sidebarCollapsed}>{children}</SideBarProvider>
              </ZoneProvider>
            </MuiProvider>
          </RTLProvider>
        </ReactQueryProvider>
      </NextAuthProvider>
    </I18nProviderClient>
  );
}
