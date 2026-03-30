"use client"

import { ShoppingCart } from "lucide-react"
import { useTranslations, useLocale } from "next-intl"
import Link from "next/link"
import { useCart, useClearCart } from "@/lib/api/cartApi"
import { CartLineItem } from "@/components/cart/CartLineItem"
import { CouponInput } from "@/components/cart/CouponInput"
import { OrderSummary } from "@/components/cart/OrderSummary"

export function CartPageClient() {
  const t = useTranslations("cart")
  const locale = useLocale()
  const { data: cart, isLoading } = useCart()
  const clear = useClearCart()

  if (isLoading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        {t("loading")}
      </div>
    )
  }

  if (!cart || cart.item_count === 0) {
    return (
      <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <ShoppingCart className="size-16 text-muted-foreground/30" />
        <h1 className="text-xl font-semibold">{t("empty_title")}</h1>
        <p className="text-sm text-muted-foreground">{t("empty")}</p>
        <Link
          href={`/${locale}`}
          className="rounded-lg bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          {t("continue_shopping")}
        </Link>
      </div>
    )
  }

  return (
    <main className="mx-auto max-w-5xl px-4 py-8">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold">
          {t("page_title")}
          <span className="ms-2 text-base font-normal text-muted-foreground">
            ({t("item_count", { count: cart.item_count })})
          </span>
        </h1>
        <button
          onClick={() => clear.mutate()}
          disabled={clear.isPending}
          className="text-sm text-destructive hover:underline disabled:opacity-50"
        >
          {t("clear_cart")}
        </button>
      </div>

      <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
        {/* Items list */}
        <section aria-label={t("items_section")}>
          <ul role="list">
            {cart.items.map((item) => (
              <CartLineItem key={item.id} item={item} />
            ))}
          </ul>
        </section>

        {/* Order summary sidebar */}
        <aside className="space-y-4 lg:sticky lg:top-4 self-start">
          <div className="rounded-xl border border-border p-4 space-y-4">
            <h2 className="font-semibold">{t("order_summary")}</h2>
            <CouponInput appliedCode={cart.coupon_code} />
            <OrderSummary cart={cart} />
            <Link
              href={`/${locale}/checkout`}
              className="block w-full rounded-lg bg-primary py-3 text-center text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors"
            >
              {t("proceed_to_checkout")}
            </Link>
            <Link
              href={`/${locale}`}
              className="block text-center text-sm text-muted-foreground hover:underline"
            >
              {t("continue_shopping")}
            </Link>
          </div>
        </aside>
      </div>
    </main>
  )
}
