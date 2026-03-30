import { getTranslations } from "next-intl/server"
import type { Metadata } from "next"
import { OrderDetailClient } from "./OrderDetailClient"

interface Props {
  params: Promise<{ orderNumber: string }>
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { orderNumber } = await params
  const t = await getTranslations("orders")
  return { title: `${t("order_number", { number: orderNumber })}` }
}

export default async function OrderDetailPage({ params }: Props) {
  const { orderNumber } = await params
  return <OrderDetailClient orderNumber={orderNumber} />
}
