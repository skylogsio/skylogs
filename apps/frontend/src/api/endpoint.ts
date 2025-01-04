import axios from "@/utils/axios";

const ENDPOINT_URL = "endpoint";

export function createEndpoint(body: unknown) {
  return axios.post(process.env.NEXT_PUBLIC_BASE_URL + ENDPOINT_URL, body);
}
