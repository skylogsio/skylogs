import { type PropsWithChildren } from "react";

// eslint-disable-next-line import/order
import { ZoneProvider } from "@/context/ZoneContext";
import { I18nProviderClient } from "@/locales/client";

import MuiProvider from "./MuiProvider";
import NextAuthProvider from "./NextAuthProvider";
import ReactQueryProvider from "./ReactQueryProvider";
import RTLProvider from "./RTLProvider";

export default function Provider({ children, locale }: PropsWithChildren<{ locale: string }>) {
  return (
    <I18nProviderClient locale={locale}>
      <NextAuthProvider>
        <ReactQueryProvider>
          <RTLProvider locale={locale}>
            <MuiProvider>
              <ZoneProvider>{children}</ZoneProvider>
            </MuiProvider>
          </RTLProvider>
        </ReactQueryProvider>
      </NextAuthProvider>
    </I18nProviderClient>
  );
}
