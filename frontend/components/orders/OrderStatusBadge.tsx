"use client"

import { useTranslations } from "next-intl"
import type { Order } from "@/schemas/orders"

type Status = Order["order_status"]

const statusStyles: Record<Status, string> = {
  pending:   "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300",
  initiated: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300",
  paid:      "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300",
  failed:    "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300",
  cancelled: "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400",
  refunded:  "bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300",
  fulfilled: "bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300",
}

export function OrderStatusBadge({ status }: { status: Status }) {
  const t = useTranslations("orders")
  const key = `status_${status}` as const

  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${statusStyles[status]}`}
    >
      {t(key as Parameters<typeof t>[0])}
    </span>
  )
}
