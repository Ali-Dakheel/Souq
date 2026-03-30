"use client"

import { useTranslations } from "next-intl"

interface Props {
  oldFils: number
  newFils: number
  locale?: string
}

export function PriceChangedWarning({ oldFils, newFils, locale = "ar-BH" }: Props) {
  const t = useTranslations("cart")
  const fmt = (f: number) =>
    new Intl.NumberFormat(locale, {
      style: "currency",
      currency: "BHD",
      minimumFractionDigits: 3,
    }).format(f / 1000)

  return (
    <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">
      {t("price_changed", { old: fmt(oldFils), new: fmt(newFils) })}
    </p>
  )
}
