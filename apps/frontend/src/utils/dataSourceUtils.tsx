import { blue, grey, orange, red, lightBlue } from "@mui/material/colors";
import { type IconType } from "react-icons";
import { SiGrafana, SiMetabase, SiPrometheus, SiSentry, SiVictoriametrics } from "react-icons/si";

import ElasticIcon from "@/assets/svg/ElasticIcon";
import PerconaIcon from "@/assets/svg/PerconaIcon";
import SplunkIcon from "@/assets/svg/SplunkIcon";
import ZabbixIcon from "@/assets/svg/ZabbixIcon";

export type DataSourceType =
  | "prometheus"
  | "sentry"
  | "grafana"
  | "metabase"
  | "elastic"
  | "zabbix"
  | "splunk"
  | "victoriametrics"
  | "victoria_logs"
  | "pmm";

export const DATA_SOURCE_VARIANTS: Record<
  DataSourceType,
  {
    label: string;
    Icon: IconType;
    defaultColor: string;
    defaultSize: string;
  }
> = {
  prometheus: {
    label: "Prometheus",
    Icon: SiPrometheus,
    defaultColor: red[500],
    defaultSize: "1.2rem"
  },
  sentry: {
    label: "Sentry",
    Icon: SiSentry,
    defaultColor: grey[700],
    defaultSize: "1.2rem"
  },
  grafana: {
    label: "Grafana",
    Icon: SiGrafana,
    defaultColor: orange[500],
    defaultSize: "1.2rem"
  },
  metabase: {
    label: "Metabase",
    Icon: SiMetabase,
    defaultColor: blue[600],
    defaultSize: "1.2rem"
  },
  elastic: {
    label: "Elastic",
    Icon: ElasticIcon as IconType,
    defaultColor: "",
    defaultSize: "1.2rem"
  },
  zabbix: {
    label: "Zabbix",
    Icon: ZabbixIcon as IconType,
    defaultColor: "",
    defaultSize: "1.2rem"
  },
  splunk: {
    label: "Splunk",
    Icon: SplunkIcon as IconType,
    defaultColor: "",
    defaultSize: "1.2rem"
  },
  victoriametrics: {
    label: "Victoria Metrics",
    Icon: SiVictoriametrics,
    defaultColor: "",
    defaultSize: "1.2rem"
  },
  pmm: {
    label: "Percona PMM",
    Icon: PerconaIcon as IconType,
    defaultColor: "",
    defaultSize: "1.2rem"
  },
  victoria_logs: {
    label: "Victoria Logs",
    Icon: SiVictoriametrics,
    defaultColor: lightBlue[600],
    defaultSize: "1.2rem"
  }
};
