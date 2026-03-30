"use client"

import { useEffect, useRef } from "react"
import { X, ShoppingCart } from "lucide-react"
import { LazyMotion, domAnimation, m, AnimatePresence } from "motion/react"
import { useTranslations, useLocale } from "next-intl"
import { useRouter } from "next/navigation"
import { useCartStore } from "@/stores/cartStore"
import { useCart } from "@/lib/api/cartApi"
import { CartLineItem } from "./CartLineItem"
import { CouponInput } from "./CouponInput"
import { OrderSummary } from "./OrderSummary"

export function CartDrawer() {
  const t = useTranslations("cart")
  const locale = useLocale()
  const router = useRouter()
  const { drawerOpen, closeDrawer } = useCartStore()
  const { data: cart, isLoading } = useCart()
  const overlayRef = useRef<HTMLDivElement>(null)

  // Close on Escape
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") closeDrawer()
    }
    if (drawerOpen) window.addEventListener("keydown", onKey)
    return () => window.removeEventListener("keydown", onKey)
  }, [drawerOpen, closeDrawer])

  // Lock body scroll when open
  useEffect(() => {
    document.body.style.overflow = drawerOpen ? "hidden" : ""
    return () => {
      document.body.style.overflow = ""
    }
  }, [drawerOpen])

  const isRtl = locale === "ar"

  return (
    <LazyMotion features={domAnimation}>
      <AnimatePresence>
        {drawerOpen && (
          <>
            {/* Backdrop */}
            <m.div
              key="backdrop"
              ref={overlayRef}
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="fixed inset-0 z-40 bg-black/40"
              onClick={closeDrawer}
              aria-hidden="true"
            />

            {/* Drawer panel */}
            <m.div
              key="drawer"
              role="dialog"
              aria-modal="true"
              aria-label={t("drawer_title")}
              initial={{ x: isRtl ? "-100%" : "100%" }}
              animate={{ x: 0 }}
              exit={{ x: isRtl ? "-100%" : "100%" }}
              transition={{ type: "spring", damping: 30, stiffness: 300 }}
              className="fixed inset-y-0 end-0 z-50 flex w-full max-w-sm flex-col bg-background shadow-2xl"
            >
              {/* Header */}
              <div className="flex items-center justify-between border-b border-border px-4 py-3">
                <h2 className="font-semibold">{t("drawer_title")}</h2>
                <button
                  onClick={closeDrawer}
                  aria-label={t("close_drawer")}
                  className="rounded p-1 hover:bg-muted transition-colors"
                >
                  <X className="size-5" />
                </button>
              </div>

              {/* Body */}
              <div className="flex-1 overflow-y-auto px-4">
                {isLoading ? (
                  <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                    {t("loading")}
                  </div>
                ) : !cart || cart.item_count === 0 ? (
                  <div className="flex h-full flex-col items-center justify-center gap-3 text-center text-muted-foreground">
                    <ShoppingCart className="size-10 opacity-30" />
                    <p className="text-sm">{t("empty")}</p>
                  </div>
                ) : (
                  <ul role="list" className="divide-y divide-border">
                    {cart.items.map((item) => (
                      <CartLineItem key={item.id} item={item} />
                    ))}
                  </ul>
                )}
              </div>

              {/* Footer */}
              {cart && cart.item_count > 0 && (
                <div className="border-t border-border px-4 py-4 space-y-4">
                  <CouponInput appliedCode={cart.coupon_code} />
                  <OrderSummary cart={cart} />
                  <button
                    onClick={() => {
                      closeDrawer()
                      router.push(`/${locale}/cart`)
                    }}
                    className="w-full rounded-lg bg-primary py-3 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors"
                  >
                    {t("view_cart")}
                  </button>
                  <button
                    onClick={() => {
                      closeDrawer()
                      router.push(`/${locale}/checkout`)
                    }}
                    className="w-full rounded-lg border border-primary py-3 text-sm font-semibold text-primary hover:bg-primary/10 transition-colors"
                  >
                    {t("checkout")}
                  </button>
                </div>
              )}
            </m.div>
          </>
        )}
      </AnimatePresence>
    </LazyMotion>
  )
}
