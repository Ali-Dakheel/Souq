"use client"

import { ShoppingCart } from "lucide-react"
import { useCartStore } from "@/stores/cartStore"
import { useTranslations } from "next-intl"

export function CartButton() {
  const t = useTranslations("cart")
  const { itemCount, openDrawer } = useCartStore()

  return (
    <button
      onClick={openDrawer}
      aria-label={t("open_cart", { count: itemCount })}
      className="relative rounded-lg p-2 hover:bg-muted transition-colors"
    >
      <ShoppingCart className="size-5" />
      {itemCount > 0 && (
        <span className="absolute -end-0.5 -top-0.5 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground">
          {itemCount > 99 ? "99+" : itemCount}
        </span>
      )}
    </button>
  )
}
