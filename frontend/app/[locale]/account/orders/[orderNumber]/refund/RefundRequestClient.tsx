"use client"

import { useState } from "react"
import { useTranslations, useLocale } from "next-intl"
import { useRouter } from "next/navigation"
import Link from "next/link"
import { useOrder } from "@/lib/api/ordersApi"
import { useOrderPayment, useRequestRefund } from "@/lib/api/paymentsApi"
import { formatBHD } from "@/lib/currency"

const REFUND_REASONS = [
  { value: "customer_request", key: "reason_cancel" },
  { value: "duplicate_charge", key: "reason_duplicate" },
  { value: "other", key: "reason_other" },
] as const

interface Props {
  orderNumber: string
}

export function RefundRequestClient({ orderNumber }: Props) {
  const t = useTranslations("payment")
  const tc = useTranslations("common")
  const locale = useLocale()
  const router = useRouter()

  const { data: order, isLoading: orderLoading } = useOrder(orderNumber)
  const { data: transaction, isLoading: paymentLoading } = useOrderPayment(
    order?.id ?? null,
    !!order,
  )

  const [reason, setReason] = useState<string>("customer_request")
  const [notes, setNotes] = useState("")
  const [submitError, setSubmitError] = useState<string | null>(null)

  const requestRefund = useRequestRefund(transaction?.id ?? 0)

  const isLoading = orderLoading || paymentLoading
  const fmtLocale = locale === "ar" ? "ar-BH" : "en-BH"

  if (isLoading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        {tc("loading")}
      </div>
    )
  }

  // Guard: only paid orders can be refunded
  if (!order || order.order_status !== "paid") {
    return (
      <main className="mx-auto max-w-lg px-4 py-16 text-center">
        <p className="mb-4 text-sm text-destructive">{t("refund_not_available")}</p>
        <Link href={`/${locale}/account/orders`} className="text-sm text-primary hover:underline">
          {t("go_to_orders")}
        </Link>
      </main>
    )
  }

  if (!transaction || transaction.status !== "captured") {
    return (
      <main className="mx-auto max-w-lg px-4 py-16 text-center">
        <p className="mb-4 text-sm text-destructive">{t("refund_no_payment")}</p>
        <Link href={`/${locale}/account/orders/${orderNumber}`} className="text-sm text-primary hover:underline">
          {t("back_to_order")}
        </Link>
      </main>
    )
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSubmitError(null)

    requestRefund.mutate(
      { reason: reason as "customer_request" | "duplicate_charge" | "other", notes: notes || undefined },
      {
        onSuccess: () => {
          router.push(`/${locale}/account/orders/${orderNumber}`)
        },
        onError: (err: unknown) => {
          const error = err as { errors?: Record<string, string[]>; message?: string }
          const fieldError = Object.values(error.errors ?? {})?.[0]?.[0]
          setSubmitError(fieldError ?? error.message ?? t("refund_error"))
        },
      },
    )
  }

  return (
    <main className="mx-auto max-w-lg px-4 py-8">
      <div className="mb-6">
        <Link
          href={`/${locale}/account/orders/${orderNumber}`}
          className="text-sm text-muted-foreground hover:text-foreground"
        >
          &larr; {t("back_to_order")}
        </Link>
      </div>

      <h1 className="mb-2 text-xl font-bold">{t("refund_title")}</h1>
      <p className="mb-6 text-sm text-muted-foreground">
        {t("refund_subtitle", { orderNumber: order.order_number })}
      </p>

      {/* Refund amount */}
      <div className="mb-6 rounded-xl border border-border p-4">
        <dl className="flex justify-between text-sm">
          <dt className="text-muted-foreground">{t("refund_amount")}</dt>
          <dd className="font-semibold">{formatBHD(order.total_fils, fmtLocale)}</dd>
        </dl>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Reason */}
        <fieldset className="space-y-3">
          <legend className="text-sm font-semibold">{t("refund_reason_label")}</legend>
          {REFUND_REASONS.map(({ value, key }) => (
            <label
              key={value}
              className="flex cursor-pointer items-center gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-muted/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5"
            >
              <input
                type="radio"
                name="reason"
                value={value}
                checked={reason === value}
                onChange={() => setReason(value)}
                className="accent-primary"
              />
              <span className="text-sm">{t(key)}</span>
            </label>
          ))}
        </fieldset>

        {/* Notes */}
        <label className="block space-y-1.5">
          <span className="text-sm font-semibold">{t("refund_notes_label")}</span>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder={t("refund_notes_placeholder")}
            rows={3}
            maxLength={1000}
            className="w-full rounded-lg border border-border bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </label>

        {submitError && (
          <p className="text-sm text-destructive">{submitError}</p>
        )}

        <button
          type="submit"
          disabled={requestRefund.isPending}
          className="w-full rounded-lg bg-primary py-3 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
        >
          {requestRefund.isPending ? t("submitting_refund") : t("submit_refund")}
        </button>
      </form>
    </main>
  )
}
