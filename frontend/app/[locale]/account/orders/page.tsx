import { getTranslations } from "next-intl/server"
import type { Metadata } from "next"
import { OrdersPageClient } from "./OrdersPageClient"

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("orders")
  return { title: t("page_title") }
}

export default function OrdersPage() {
  return <OrdersPageClient />
}
