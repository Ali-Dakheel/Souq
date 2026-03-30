import { getTranslations } from "next-intl/server"
import type { Metadata } from "next"
import { CheckoutResultClient } from "./CheckoutResultClient"

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("payment")
  return { title: t("result_title") }
}

export default function CheckoutResultPage() {
  return <CheckoutResultClient />
}
