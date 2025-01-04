import { type InstalledLocalesType } from "@/locales/client";
import wrapper_en from "@/locales/en/components/wrapper";
import wrapper_fa from "@/locales/fa/components/wrapper";

type ProfileListTitleType = `list.${string &
  keyof typeof wrapper_en.wrapper.profile.list &
  keyof typeof wrapper_fa.wrapper.profile.list}`;

export type ProfileListType = { title: ProfileListTitleType; iconSRC: string };

export type LocalesListType = { locale: InstalledLocalesType; title: string; iconSRC: string };
