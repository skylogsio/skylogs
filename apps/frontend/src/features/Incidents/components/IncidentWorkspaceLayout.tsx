"use client";

import type { ReactNode } from "react";

import { LayoutGroup } from "framer-motion";

type IncidentWorkspaceLayoutProps = {
  children: ReactNode;
};

export default function IncidentWorkspaceLayout({ children }: IncidentWorkspaceLayoutProps) {
  return <LayoutGroup id="incident-workspace">{children}</LayoutGroup>;
}
