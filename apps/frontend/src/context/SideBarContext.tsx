"use client";

import { createContext, useContext, useEffect, useState, type PropsWithChildren } from "react";

export const SIDEBAR_COLLAPSED_COOKIE = "skylogs-sidebar-collapsed";

interface SideBarContextType {
  collapsed: boolean;
  setCollapsed: (collapsed: boolean) => void;
  toggleCollapsed: () => void;
}

const SideBarContext = createContext<SideBarContextType | undefined>(undefined);

function hasCollapsedCookie() {
  if (typeof document === "undefined") return false;
  return document.cookie
    .split(";")
    .some((part) => part.trim().startsWith(`${SIDEBAR_COLLAPSED_COOKIE}=`));
}

function writeCollapsedCookie(value: boolean) {
  if (typeof document === "undefined") return;

  const expires = new Date();
  expires.setFullYear(expires.getFullYear() + 1);
  document.cookie = `${SIDEBAR_COLLAPSED_COOKIE}=${value}; path=/; expires=${expires.toUTCString()}; SameSite=Lax`;

  try {
    window.localStorage.setItem(SIDEBAR_COLLAPSED_COOKIE, String(value));
  } catch {
    // ignore storage access errors
  }
}

interface SideBarProviderProps extends PropsWithChildren {
  initialCollapsed?: boolean;
}

export function SideBarProvider({ children, initialCollapsed = false }: SideBarProviderProps) {
  const [collapsed, setCollapsedState] = useState(initialCollapsed);

  // One-time migration from the previous localStorage-only approach
  useEffect(() => {
    if (hasCollapsedCookie()) return;

    try {
      const stored = window.localStorage.getItem(SIDEBAR_COLLAPSED_COOKIE);
      if (stored === "true" || stored === "false") {
        const value = stored === "true";
        writeCollapsedCookie(value);
        setCollapsedState(value);
      }
    } catch {
      // ignore storage access errors
    }
  }, []);

  function setCollapsed(value: boolean) {
    setCollapsedState(value);
    writeCollapsedCookie(value);
  }

  function toggleCollapsed() {
    setCollapsedState((prev) => {
      const next = !prev;
      writeCollapsedCookie(next);
      return next;
    });
  }

  return (
    <SideBarContext.Provider value={{ collapsed, setCollapsed, toggleCollapsed }}>
      {children}
    </SideBarContext.Provider>
  );
}

export function useSideBar() {
  const context = useContext(SideBarContext);
  if (context === undefined) {
    throw new Error("useSideBar must be used within a SideBarProvider");
  }
  return context;
}
