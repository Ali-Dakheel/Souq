"use client"

import { useState } from "react"
import { useTranslations } from "next-intl"
import { useCancelOrder } from "@/lib/api/ordersApi"

interface Props {
  orderNumber: string
  onCancelled?: () => void
}

export function CancelOrderDialog({ orderNumber, onCancelled }: Props) {
  const t = useTranslations("orders")
  const tc = useTranslations("common")
  const [open, setOpen] = useState(false)
  const [reason, setReason] = useState("")
  const cancel = useCancelOrder(orderNumber)

  function handleSubmit() {
    cancel.mutate(
      { reason: reason || undefined },
      {
        onSuccess: () => {
          setOpen(false)
          setReason("")
          onCancelled?.()
        },
      },
    )
  }

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="rounded-lg border border-destructive px-4 py-2 text-sm font-medium text-destructive transition-colors hover:bg-destructive/10"
      >
        {t("cancel_order")}
      </button>

      {open && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="cancel-dialog-title"
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        >
          <div className="w-full max-w-sm rounded-xl bg-background p-6 shadow-xl">
            <h2
              id="cancel-dialog-title"
              className="mb-2 text-base font-semibold"
            >
              {t("cancel_confirm_title")}
            </h2>
            <p className="mb-4 text-sm text-muted-foreground">
              {t("cancel_confirm_desc")}
            </p>

            <label className="block space-y-1.5">
              <span className="text-sm font-medium">
                {t("cancel_reason_label")}
              </span>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder={t("cancel_reason_placeholder")}
                rows={3}
                className="w-full rounded-lg border border-border bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
              />
            </label>

            <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <button
                onClick={() => setOpen(false)}
                className="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors hover:bg-muted"
              >
                {tc("cancel")}
              </button>
              <button
                onClick={handleSubmit}
                disabled={cancel.isPending}
                className="rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground transition-colors hover:bg-destructive/90 disabled:opacity-50"
              >
                {cancel.isPending ? t("cancelling") : t("confirm_cancel")}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
