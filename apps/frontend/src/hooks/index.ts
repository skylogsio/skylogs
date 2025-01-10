import Locale from "intl-locale-textinfo-polyfill";

import { useCurrentLocale } from "@/locales/client";

export function useCurrentDirection() {
  const locale = useCurrentLocale();
  const { direction } = new Locale(locale).textInfo;
  return direction;
}
