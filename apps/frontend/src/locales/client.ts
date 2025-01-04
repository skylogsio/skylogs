"use client";
import { createI18nClient } from "next-international/client";

export type InstalledLocalesType = "fa" | "en";

export const { useI18n, useScopedI18n, I18nProviderClient, useCurrentLocale, useChangeLocale } =
  createI18nClient({
    en: () => import("./en"),
    fa: () => import("./fa")
  });
