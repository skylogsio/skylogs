import { redirect } from "next/navigation";

export default async function Home() {
  redirect("/alert-rule");
  return null;
}
