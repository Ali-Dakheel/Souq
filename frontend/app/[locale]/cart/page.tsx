import type { Metadata } from "next"
import { getTranslations } from "next-intl/server"
import { CartPageClient } from "./CartPageClient"

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("cart")
  return { title: t("page_title") }
}

export default function CartPage() {
  return <CartPageClient />
}
