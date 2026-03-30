"use client"

import { Minus, Plus, Trash2 } from "lucide-react"
import { useTranslations, useLocale } from "next-intl"
import { formatBHD } from "@/lib/currency"
import { PriceChangedWarning } from "./PriceChangedWarning"
import { useRemoveCartItem, useUpdateCartItem } from "@/lib/api/cartApi"
import type { CartItem } from "@/schemas/cart"

interface Props {
  item: CartItem
}

export function CartLineItem({ item }: Props) {
  const t = useTranslations("cart")
  const locale = useLocale()
  const update = useUpdateCartItem()
  const remove = useRemoveCartItem()

  function handleQtyChange(delta: number) {
    const next = item.quantity + delta
    if (next < 1) return
    update.mutate({ itemId: item.id, input: { quantity: next } })
  }

  return (
    <li className="flex gap-4 py-4 border-b border-border last:border-0">
      {/* Product info */}
      <div className="flex-1 min-w-0">
        <p className="font-medium text-sm truncate">{item.product_name}</p>
        {item.variant_label && (
          <p className="text-xs text-muted-foreground mt-0.5">
            {item.variant_label}
          </p>
        )}
        {item.price_changed && (
          <PriceChangedWarning
            oldFils={item.price_fils_snapshot}
            newFils={item.current_price_fils}
            locale={locale === "ar" ? "ar-BH" : "en-BH"}
          />
        )}
        <p className="text-sm font-semibold mt-1">
          {formatBHD(item.line_total_fils, locale === "ar" ? "ar-BH" : "en-BH")}
        </p>
      </div>

      {/* Quantity controls */}
      <div className="flex flex-col items-end gap-2">
        <button
          onClick={() => remove.mutate(item.id)}
          disabled={remove.isPending}
          aria-label={t("remove_item")}
          className="text-muted-foreground hover:text-destructive transition-colors"
        >
          <Trash2 className="size-4" />
        </button>
        <div className="flex items-center gap-2">
          <button
            onClick={() => handleQtyChange(-1)}
            disabled={update.isPending || item.quantity <= 1}
            aria-label={t("decrease_qty")}
            className="size-7 rounded border border-border flex items-center justify-center disabled:opacity-40 hover:bg-muted transition-colors"
          >
            <Minus className="size-3" />
          </button>
          <span className="w-6 text-center text-sm tabular-nums">
            {item.quantity}
          </span>
          <button
            onClick={() => handleQtyChange(1)}
            disabled={update.isPending}
            aria-label={t("increase_qty")}
            className="size-7 rounded border border-border flex items-center justify-center disabled:opacity-40 hover:bg-muted transition-colors"
          >
            <Plus className="size-3" />
          </button>
        </div>
      </div>
    </li>
  )
}
