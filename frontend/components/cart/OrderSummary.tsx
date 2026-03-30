"use client"

import { useTranslations, useLocale } from "next-intl"
import { formatBHD } from "@/lib/currency"
import type { Cart } from "@/schemas/cart"

interface Props {
  cart: Cart
}

export function OrderSummary({ cart }: Props) {
  const t = useTranslations("cart")
  const locale = useLocale()
  const fmt = (fils: number) =>
    formatBHD(fils, locale === "ar" ? "ar-BH" : "en-BH")

  return (
    <div className="space-y-2 text-sm">
      <div className="flex justify-between">
        <span className="text-muted-foreground">{t("subtotal")}</span>
        <span>{fmt(cart.subtotal_fils)}</span>
      </div>

      {cart.discount_fils > 0 && (
        <div className="flex justify-between text-green-600 dark:text-green-400">
          <span>
            {t("discount")}
            {cart.coupon_code && (
              <span className="ms-1 text-xs opacity-75">
                ({cart.coupon_code})
              </span>
            )}
          </span>
          <span>- {fmt(cart.discount_fils)}</span>
        </div>
      )}

      <div className="flex justify-between">
        <span className="text-muted-foreground">{t("vat", { rate: "10%" })}</span>
        <span>{fmt(cart.vat_fils)}</span>
      </div>

      <div className="flex justify-between border-t border-border pt-2 font-semibold">
        <span>{t("total")}</span>
        <span>{fmt(cart.total_fils)}</span>
      </div>
    </div>
  )
}
