import { getTranslations } from "next-intl/server"
import type { Metadata } from "next"
import { RefundRequestClient } from "./RefundRequestClient"

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("payment")
  return { title: t("refund_title") }
}

export default async function RefundPage({
  params,
}: {
  params: Promise<{ orderNumber: string }>
}) {
  const { orderNumber } = await params
  return <RefundRequestClient orderNumber={orderNumber} />
}
