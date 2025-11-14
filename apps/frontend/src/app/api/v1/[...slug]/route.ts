import { NextRequest, NextResponse } from "next/server";

import axios, { isAxiosError } from "axios";

const allowedRoutes = new Set([
  "fire-alert",
  "resolve-alert",
  "status-alert",
  "notification-alert",
  "stop-alert",
  "sentry-alert",
  "splunk-alert",
  "zabbix-alert",
  "grafana-alert",
  "pmm-alert"
]);

export async function POST(req: NextRequest, { params }: { params: { slug: string[] } }) {
  const slugParts = params.slug || [];

  if (slugParts.length === 0) {
    return NextResponse.json({ message: "Route not found" }, { status: 404 });
  }

  const [baseRoute, token] = slugParts;
  const isTokenRoute = slugParts.length === 2;

  if (!allowedRoutes.has(baseRoute)) {
    return NextResponse.json({ message: "Route not found" }, { status: 404 });
  }

  try {
    const body = await req.json();
    const headers: Record<string, string> = {};
    req.headers.forEach((value, key) => {
      if (key !== "host" && key !== "content-length") {
        headers[key] = value;
      }
    });
    const baseUrl = process.env.BASE_URL ;
    const fullPath = isTokenRoute ? `${baseRoute}/${token}` : `${baseRoute}`;
    const targetUrl = `${baseUrl}${fullPath}`;
    const response = await axios.post(targetUrl, body, { headers });

    return NextResponse.json(response.data, { status: response.status });
  } catch (error: unknown) {
    if (isAxiosError(error)) {
      console.error("Error forwarding:", error.message);
      const status = error.response?.status || 500;
      const data = error.response?.data || { message: "Internal Server Error" };
      return NextResponse.json(data, { status });
    }
  }
}
