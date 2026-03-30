"use client"

import { useTranslations, useLocale } from "next-intl"
import Link from "next/link"
import { useOrder } from "@/lib/api/ordersApi"
import { OrderStatusBadge } from "@/components/orders/OrderStatusBadge"
import { OrderStatusTimeline } from "@/components/orders/OrderStatusTimeline"
import { CancelOrderDialog } from "@/components/orders/CancelOrderDialog"
import { formatBHD } from "@/lib/currency"
import type { Order } from "@/schemas/orders"

const CANCELLABLE_STATUSES: Order["order_status"][] = ["pending", "initiated"]

interface Props {
  orderNumber: string
}

export function OrderDetailClient({ orderNumber }: Props) {
  const t = useTranslations("orders")
  const tc = useTranslations("common")
  const locale = useLocale()
  const { data: order, isLoading, isError } = useOrder(orderNumber)

  if (isLoading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        {tc("loading")}
      </div>
    )
  }

  if (isError || !order) {
    return (
      <div className="flex min-h-[40vh] flex-col items-center justify-center gap-3">
        <p className="text-sm text-destructive">{tc("error")}</p>
        <Link href={`/${locale}/account/orders`} className="text-sm text-primary hover:underline">
          {t("back_to_orders")}
        </Link>
      </div>
    )
  }

  const isCancellable = CANCELLABLE_STATUSES.includes(order.order_status)

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      <div className="mb-6 flex items-center gap-3">
        <Link
          href={`/${locale}/account/orders`}
          className="text-sm text-muted-foreground hover:text-foreground"
        >
          ← {t("back_to_orders")}
        </Link>
      </div>

      <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold">
            {t("order_number", { number: order.order_number })}
          </h1>
          <p className="text-sm text-muted-foreground">
            {t("placed_on", {
              date: new Date(order.created_at).toLocaleDateString(
                locale === "ar" ? "ar-BH" : "en-BH",
                { dateStyle: "long" },
              ),
            })}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <OrderStatusBadge status={order.order_status} />
          {isCancellable && (
            <CancelOrderDialog orderNumber={order.order_number} />
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_300px]">
        {/* Left: items + timeline */}
        <div className="space-y-6">
          {/* Order items */}
          <section className="rounded-xl border border-border p-4">
            <h2 className="mb-3 text-sm font-semibold">{t("order_items")}</h2>
            <ul role="list" className="divide-y divide-border">
              {order.items?.map((item, i) => (
                <li key={i} className="flex justify-between gap-4 py-3 text-sm">
                  <div>
                    <p className="font-medium">
                      {typeof item.product_name === "string"
                        ? item.product_name
                        : locale === "ar"
                          ? item.product_name.ar
                          : item.product_name.en}
                    </p>
                    <p className="text-xs text-muted-foreground">{item.sku}</p>
                    {item.variant_attributes &&
                      Object.entries(item.variant_attributes).map(([k, v]) => (
                        <p key={k} className="text-xs text-muted-foreground">
                          {k}: {v}
                        </p>
                      ))}
                  </div>
                  <div className="text-end">
                    <p>{formatBHD(item.price_fils_per_unit, locale === "ar" ? "ar-BH" : "en-BH")}</p>
                    <p className="text-xs text-muted-foreground">× {item.quantity}</p>
                    <p className="font-medium">
                      {formatBHD(item.total_fils, locale === "ar" ? "ar-BH" : "en-BH")}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          </section>

          {/* Status timeline */}
          {order.status_history && order.status_history.length > 0 && (
            <section className="rounded-xl border border-border p-4">
              <OrderStatusTimeline history={order.status_history} />
            </section>
          )}
        </div>

        {/* Right: totals + addresses */}
        <div className="space-y-4">
          {/* Totals */}
          <div className="rounded-xl border border-border p-4">
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between">
                <dt className="text-muted-foreground">{t("subtotal")}</dt>
                <dd>{formatBHD(order.subtotal_fils, locale === "ar" ? "ar-BH" : "en-BH")}</dd>
              </div>
              {order.coupon_discount_fils > 0 && (
                <div className="flex justify-between text-green-600">
                  <dt>{t("discount")}</dt>
                  <dd>−{formatBHD(order.coupon_discount_fils, locale === "ar" ? "ar-BH" : "en-BH")}</dd>
                </div>
              )}
              <div className="flex justify-between">
                <dt className="text-muted-foreground">{t("vat")}</dt>
                <dd>{formatBHD(order.vat_fils, locale === "ar" ? "ar-BH" : "en-BH")}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-muted-foreground">{t("delivery")}</dt>
                <dd>{formatBHD(order.delivery_fee_fils, locale === "ar" ? "ar-BH" : "en-BH")}</dd>
              </div>
              <div className="flex justify-between border-t border-border pt-2 font-semibold">
                <dt>{t("total")}</dt>
                <dd>{formatBHD(order.total_fils, locale === "ar" ? "ar-BH" : "en-BH")}</dd>
              </div>
            </dl>
          </div>

          {/* Payment method */}
          <div className="rounded-xl border border-border p-4 text-sm">
            <p className="mb-1 font-semibold">{t("payment_method")}</p>
            <p className="text-muted-foreground capitalize">{order.payment_method.replace(/_/g, " ")}</p>
          </div>

          {/* Shipping address */}
          {order.shipping_address && (
            <div className="rounded-xl border border-border p-4 text-sm">
              <p className="mb-2 font-semibold">{t("shipping_address")}</p>
              <address className="not-italic leading-snug text-muted-foreground">
                <p>{order.shipping_address.recipient_name}</p>
                <p>{order.shipping_address.street_address}</p>
                <p>
                  {order.shipping_address.district}, {order.shipping_address.governorate}
                </p>
                <p>{order.shipping_address.phone}</p>
              </address>
            </div>
          )}

          {/* Notes */}
          {order.notes && (
            <div className="rounded-xl border border-border p-4 text-sm">
              <p className="mb-1 font-semibold">{t("notes")}</p>
              <p className="text-muted-foreground">{order.notes}</p>
            </div>
          )}
        </div>
      </div>
    </main>
  )
}
