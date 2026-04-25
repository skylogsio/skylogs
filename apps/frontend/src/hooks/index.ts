import { useCallback } from "react";

import { useQuery } from "@tanstack/react-query";
import Locale from "intl-locale-textinfo-polyfill";
import { useSession } from "next-auth/react";

import { getMyInfo } from "@/api/profile";
import { useCurrentLocale } from "@/locales/client";
import type { RoleType } from "@/utils/userUtils";

export function useCurrentDirection() {
  const locale = useCurrentLocale();
  const { direction } = new Locale(locale).textInfo;
  return direction;
}

export function useRole() {
  const { data } = useSession();
  const { data: userInfo } = useQuery({
    queryKey: ["profile"],
    queryFn: () => getMyInfo(),
    enabled: Boolean(data)
  });

  const hasRole = useCallback(
    (roles: RoleType | RoleType[]): boolean => {
      if (userInfo) {
        if (typeof roles === "string") {
          return userInfo.roles.includes(roles);
        } else {
          return roles.some((role) => userInfo.roles.includes(role));
        }
      }
      return false;
    },
    [userInfo]
  );

  return { hasRole, userInfo };
}
