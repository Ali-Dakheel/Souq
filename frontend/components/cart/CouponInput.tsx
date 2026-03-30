"use client"

import { useState } from "react"
import { X } from "lucide-react"
import { useTranslations } from "next-intl"
import { useApplyCoupon, useRemoveCoupon } from "@/lib/api/cartApi"

interface Props {
  appliedCode: string | null
}

export function CouponInput({ appliedCode }: Props) {
  const t = useTranslations("cart")
  const [code, setCode] = useState("")
  const [validationError, setValidationError] = useState<string | null>(null)
  const apply = useApplyCoupon()
  const remove = useRemoveCoupon()

  function handleApply(e: React.FormEvent) {
    e.preventDefault()
    setValidationError(null)
    apply.mutate(code.trim().toUpperCase(), {
      onSuccess: () => setCode(""),
      onError: (err: unknown) => {
        const e = err as { errors?: { coupon_code?: string[] } }
        setValidationError(
          e.errors?.coupon_code?.[0] ?? t("coupon_invalid"),
        )
      },
    })
  }

  if (appliedCode) {
    return (
      <div className="flex items-center justify-between rounded-lg bg-green-50 dark:bg-green-950 px-3 py-2 text-sm">
        <span className="font-medium text-green-700 dark:text-green-300">
          {appliedCode}
        </span>
        <button
          onClick={() => remove.mutate()}
          disabled={remove.isPending}
          aria-label={t("remove_coupon")}
          className="ms-2 text-green-700 dark:text-green-300 hover:text-green-900 dark:hover:text-green-100 transition-colors"
        >
          <X className="size-4" />
        </button>
      </div>
    )
  }

  return (
    <form onSubmit={handleApply} className="flex gap-2">
      <input
        type="text"
        value={code}
        onChange={(e) => {
          setCode(e.target.value)
          setValidationError(null)
        }}
        placeholder={t("coupon_placeholder")}
        className="flex-1 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
      />
      <button
        type="submit"
        disabled={apply.isPending || !code.trim()}
        className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50 hover:bg-primary/90 transition-colors"
      >
        {apply.isPending ? t("applying") : t("apply_coupon")}
      </button>
      {validationError && (
        <p className="mt-1 text-xs text-destructive">{validationError}</p>
      )}
    </form>
  )
}
