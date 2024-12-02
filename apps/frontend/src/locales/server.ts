import Locale from "intl-locale-textinfo-polyfill";
import { createI18nServer } from "next-international/server";

export const { getI18n, getScopedI18n, getStaticParams, getCurrentLocale } = createI18nServer({
  en: () => import("./en"),
  fa: () => import("./fa")
});

export async function getCurrentDirection() {
  const locale = await getCurrentLocale();
  const { direction } = new Locale(locale).textInfo;
  return direction;
}

export async function getAcceptLanguage() {
  const language = await getCurrentLocale();
  switch (language) {
    case "fa":
      return "fa-IR";
    case "en":
      return "en-US";

    default:
      return "en-US";
  }
}
