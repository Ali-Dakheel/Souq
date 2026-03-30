"use client"

import { useTranslations, useLocale } from "next-intl"
import { useSearchParams } from "next/navigation"
import Link from "next/link"
import { usePaymentResult, useOrderPayment } from "@/lib/api/paymentsApi"
import { formatBHD } from "@/lib/currency"
import { useState, useEffect } from "react"

export function CheckoutResultClient() {
  const t = useTranslations("payment")
  const tc = useTranslations("common")
  const locale = useLocale()
  const searchParams = useSearchParams()
  const tapId = searchParams.get("tap_id")

  const {
    data: transaction,
    isLoading,
    isError,
  } = usePaymentResult(tapId)

  // If the initial result returns 'initiated' (webhook hasn't arrived yet),
  // poll the order payment status every 3s
  const [pollEnabled, setPollEnabled] = useState(false)
  const [pollCount, setPollCount] = useState(0)

  const { data: polledTransaction } = useOrderPayment(
    transaction?.order_id ?? null,
    pollEnabled,
  )

  // Determine which transaction to display
  const displayTransaction = polledTransaction?.status && polledTransaction.status !== "initiated"
    ? polledTransaction
    : transaction

  useEffect(() => {
    if (transaction?.status === "initiated") {
      setPollEnabled(true)
    } else {
      setPollEnabled(false)
    }
  }, [transaction?.status])

  // Stop polling after 30s (10 polls at 3s interval)
  useEffect(() => {
    if (!pollEnabled) return
    const interval = setInterval(() => {
      setPollCount((c) => {
        if (c >= 10) {
          setPollEnabled(false)
          return c
        }
        return c + 1
      })
    }, 3000)
    return () => clearInterval(interval)
  }, [pollEnabled])

  // Stop polling if polled transaction resolves
  useEffect(() => {
    if (polledTransaction?.status && polledTransaction.status !== "initiated") {
      setPollEnabled(false)
    }
  }, [polledTransaction?.status])

  if (!tapId) {
    return (
      <main className="mx-auto max-w-lg px-4 py-16 text-center">
        <p className="text-sm text-destructive">{t("missing_tap_id")}</p>
        <Link href={`/${locale}`} className="mt-4 inline-block text-sm text-primary hover:underline">
          {t("go_home")}
        </Link>
      </main>
    )
  }

  if (isLoading) {
    return (
      <main className="mx-auto max-w-lg px-4 py-16 text-center">
        <div className="mb-4 flex justify-center">
          <div className="h-12 w-12 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>
        <p className="text-sm text-muted-foreground">{t("verifying")}</p>
      </main>
    )
  }

  if (isError || !displayTransaction) {
    return (
      <main className="mx-auto max-w-lg px-4 py-16 text-center">
        <p className="text-sm text-destructive">{tc("error")}</p>
        <Link href={`/${locale}/account/orders`} className="mt-4 inline-block text-sm text-primary hover:underline">
          {t("go_to_orders")}
        </Link>
      </main>
    )
  }

  const status = displayTransaction.status
  const fmtLocale = locale === "ar" ? "ar-BH" : "en-BH"

  // Still initiated — show polling state
  if (status === "initiated") {
    return (
      <main className="mx-auto max-w-lg px-4 py-16 text-center">
        <div className="mb-4 flex justify-center">
          <div className="h-12 w-12 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>
        <h1 className="mb-2 text-lg font-semibold">{t("verifying")}</h1>
        <p className="mb-6 text-sm text-muted-foreground">{t("verifying_desc")}</p>
        <Link
          href={`/${locale}/account/orders`}
          className="text-sm text-primary hover:underline"
        >
          {t("go_to_orders")}
        </Link>
      </main>
    )
  }

  // Captured — success
  if (status === "captured") {
    return (
      <main className="mx-auto max-w-lg px-4 py-16 text-center">
        <div className="mb-4 flex justify-center">
          <div className="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600">
            <svg className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </div>
        <h1 className="mb-2 text-xl font-bold text-green-700">{t("success_title")}</h1>
        <p className="mb-1 text-sm text-muted-foreground">{t("success_desc")}</p>
        <p className="mb-6 text-sm font-medium">
          {formatBHD(displayTransaction.amount_fils, fmtLocale)}
        </p>
        <Link
          href={`/${locale}/account/orders`}
          className="inline-block rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
        >
          {t("view_order")}
        </Link>
      </main>
    )
  }

  // Failed
  return (
    <main className="mx-auto max-w-lg px-4 py-16 text-center">
      <div className="mb-4 flex justify-center">
        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-red-600">
          <svg className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
      </div>
      <h1 className="mb-2 text-xl font-bold text-red-700">{t("failed_title")}</h1>
      <p className="mb-1 text-sm text-muted-foreground">{t("failed_desc")}</p>
      {displayTransaction.failure_reason && (
        <p className="mb-4 text-xs text-muted-foreground">{displayTransaction.failure_reason}</p>
      )}
      <div className="flex flex-col items-center gap-3">
        <Link
          href={`/${locale}/checkout`}
          className="inline-block rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
        >
          {t("try_again")}
        </Link>
        <Link
          href={`/${locale}/account/orders`}
          className="text-sm text-primary hover:underline"
        >
          {t("go_to_orders")}
        </Link>
      </div>
    </main>
  )
}
