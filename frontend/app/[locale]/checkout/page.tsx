import { getTranslations } from "next-intl/server"
import type { Metadata } from "next"
import { CheckoutPageClient } from "./CheckoutPageClient"

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("checkout")
  return { title: t("page_title") }
}

export default function CheckoutPage() {
  return <CheckoutPageClient />
}
