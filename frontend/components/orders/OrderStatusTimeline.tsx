"use client"

import { useTranslations, useLocale } from "next-intl"
import type { OrderStatusHistory } from "@/schemas/orders"

interface Props {
  history: OrderStatusHistory[]
}

export function OrderStatusTimeline({ history }: Props) {
  const t = useTranslations("orders")
  const locale = useLocale()

  if (!history.length) return null

  return (
    <section aria-label={t("status_history")}>
      <h3 className="mb-3 text-sm font-semibold">{t("status_history")}</h3>
      <ol role="list" className="space-y-3">
        {history.map((entry, i) => (
          <li key={i} className="flex gap-3">
            <div className="mt-1 flex flex-col items-center">
              <span className="size-2.5 rounded-full bg-primary ring-2 ring-primary/20" />
              {i < history.length - 1 && (
                <span className="mt-1 h-full w-px bg-border" />
              )}
            </div>
            <div className="pb-3 text-sm leading-snug">
              <p className="font-medium">
                {t(`status_${entry.new_status}` as Parameters<typeof t>[0])}
              </p>
              {entry.reason && (
                <p className="text-muted-foreground">{entry.reason}</p>
              )}
              {entry.created_at && (
                <time
                  dateTime={entry.created_at}
                  className="text-xs text-muted-foreground"
                >
                  {new Date(entry.created_at).toLocaleString(locale === "ar" ? "ar-BH" : "en-BH", {
                    dateStyle: "medium",
                    timeStyle: "short",
                  })}
                </time>
              )}
            </div>
          </li>
        ))}
      </ol>
    </section>
  )
}
