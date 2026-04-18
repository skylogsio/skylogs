// File: apps/frontend/src/context/ZoneContext.tsx

"use client";

import { createContext, useContext, useState, type PropsWithChildren } from "react";

interface ZoneContextType {
  selectedZone: string;
  setSelectedZone: (zone: string) => void;
}

const ZoneContext = createContext<ZoneContextType | undefined>(undefined);

export function ZoneProvider({ children }: PropsWithChildren) {
  const [selectedZone, setSelectedZoneState] = useState<string>(() => {
    if (typeof document !== "undefined") {
      const cookies = document.cookie.split(";");
      const zoneCookie = cookies.find((c) => c.trim().startsWith("X-Cluster="));
      if (zoneCookie) {
        return zoneCookie.split("=")[1];
      }
    }
    return "default";
  });

  const setSelectedZone = (zone: string) => {
    setSelectedZoneState(zone);

    if (typeof document !== "undefined") {
      const expires = new Date();
      expires.setFullYear(expires.getFullYear() + 1);
      document.cookie = `X-Cluster=${zone}; path=/; expires=${expires.toUTCString()}; SameSite=Lax`;
    }
  };

  return (
    <ZoneContext.Provider value={{ selectedZone, setSelectedZone }}>
      {children}
    </ZoneContext.Provider>
  );
}

export function useZone() {
  const context = useContext(ZoneContext);
  if (context === undefined) {
    throw new Error("useZone must be used within a ZoneProvider");
  }
  return context;
}
