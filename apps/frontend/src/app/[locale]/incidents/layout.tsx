import type { ReactNode } from "react";

import IncidentWorkspaceLayout from "@/features/Incidents/components/IncidentWorkspaceLayout";

export default function IncidentsLayout({ children }: { children: ReactNode }) {
  return <IncidentWorkspaceLayout>{children}</IncidentWorkspaceLayout>;
}
