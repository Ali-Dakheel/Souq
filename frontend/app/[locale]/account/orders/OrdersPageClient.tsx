"use client"

import { useState } from "react"
import { useTranslations, useLocale } from "next-intl"
import Link from "next/link"
import { useOrders } from "@/lib/api/ordersApi"
import { OrderStatusBadge } from "@/components/orders/OrderStatusBadge"
import { formatBHD } from "@/lib/currency"
import type { Order } from "@/schemas/orders"

const STATUS_FILTERS: Array<{ key: string; value: string | undefined }> = [
  { key: "filter_all",       value: undefined },
  { key: "filter_pending",   value: "pending" },
  { key: "filter_paid",      value: "paid" },
  { key: "filter_cancelled", value: "cancelled" },
]

export function OrdersPageClient() {
  const t = useTranslations("orders")
  const locale = useLocale()
  const [status, setStatus] = useState<string | undefined>(undefined)
  const [page, setPage] = useState(1)

  const { data, isLoading } = useOrders(page, status)

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      <h1 className="mb-6 text-2xl font-bold">{t("page_title")}</h1>

      {/* Status filter tabs */}
      <div className="mb-6 flex gap-2 overflow-x-auto">
        {STATUS_FILTERS.map(({ key, value }) => (
          <button
            key={key}
            onClick={() => { setStatus(value); setPage(1) }}
            className={`rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
              status === value
                ? "bg-primary text-primary-foreground"
                : "border border-border hover:bg-muted"
            }`}
          >
            {t(key as Parameters<typeof t>[0])}
          </button>
        ))}
      </div>

      {isLoading && (
        <p className="text-sm text-muted-foreground">{t("loading")}</p>
      )}

      {!isLoading && data?.data.length === 0 && (
        <div className="flex flex-col items-center gap-3 py-16 text-center">
          <p className="text-lg font-semibold">{t("empty_title")}</p>
          <p className="text-sm text-muted-foreground">{t("empty")}</p>
        </div>
      )}

      {!isLoading && data && data.data.length > 0 && (
        <>
          <ul role="list" className="space-y-3">
            {data.data.map((order) => (
              <li
                key={order.id}
                className="rounded-xl border border-border p-4 transition-colors hover:bg-muted/30"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="space-y-1">
                    <p className="font-semibold text-sm">
                      {t("order_number", { number: order.order_number })}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {t("placed_on", {
                        date: new Date(order.created_at).toLocaleDateString(
                          locale === "ar" ? "ar-BH" : "en-BH",
                          { dateStyle: "medium" },
                        ),
                      })}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {t("items_count", { count: order.item_count })}
                    </p>
                  </div>
                  <div className="flex flex-col items-end gap-2">
                    <OrderStatusBadge status={order.order_status as Order["order_status"]} />
                    <p className="text-sm font-medium">
                      {formatBHD(order.total_fils, locale === "ar" ? "ar-BH" : "en-BH")}
                    </p>
                  </div>
                </div>
                <div className="mt-3">
                  <Link
                    href={`/${locale}/account/orders/${order.order_number}`}
                    className="text-sm font-medium text-primary hover:underline underline-offset-4"
                  >
                    {t("view_order")} →
                  </Link>
                </div>
              </li>
            ))}
          </ul>

          {/* Pagination */}
          {data.meta.last_page > 1 && (
            <div className="mt-6 flex items-center justify-center gap-2">
              <button
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page === 1}
                className="rounded-lg border border-border px-4 py-2 text-sm disabled:opacity-40"
              >
                ←
              </button>
              <span className="text-sm text-muted-foreground">
                {page} / {data.meta.last_page}
              </span>
              <button
                onClick={() => setPage((p) => Math.min(data.meta.last_page, p + 1))}
                disabled={page === data.meta.last_page}
                className="rounded-lg border border-border px-4 py-2 text-sm disabled:opacity-40"
              >
                →
              </button>
            </div>
          )}
        </>
      )}
    </main>
  )
}
