import { type DataSourceType } from "@/utils/dataSourceUtils";

export interface IDataSource {
  type: DataSourceType;
  name: string;
  url: string;
  username: string;
  password: string;
  updated_at: string;
  created_at: string;
  id: string;
  copy: string;
}
