"use client"

import { useState } from "react"
import { useTranslations, useLocale } from "next-intl"
import { useAddresses, useCheckout } from "@/lib/api/ordersApi"
import { useCreateCharge } from "@/lib/api/paymentsApi"
import { useCart } from "@/lib/api/cartApi"
import { useCheckoutStore } from "@/stores/checkoutStore"
import { AddressSelector } from "@/components/orders/AddressSelector"
import { formatBHD } from "@/lib/currency"

const PAYMENT_METHODS = [
  { value: "card",        key: "payment_card" },
  { value: "benefit",     key: "payment_benefit" },
  { value: "benefit_pay", key: "payment_benefit_pay" },
  { value: "apple_pay",   key: "payment_apple_pay" },
] as const

export function CheckoutPageClient() {
  const t = useTranslations("checkout")
  const tp = useTranslations("payment")
  const locale = useLocale()

  const { data: addresses = [], isLoading: addrLoading } = useAddresses()
  const { data: cart, isLoading: cartLoading } = useCart()

  const {
    shippingAddressId,
    billingAddressId,
    sameAsShipping,
    paymentMethod,
    notes,
    setShippingAddress,
    setBillingAddress,
    setSameAsShipping,
    setPaymentMethod,
    setNotes,
  } = useCheckoutStore()

  const checkout = useCheckout()
  const createCharge = useCreateCharge()
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  const isLoading = addrLoading || cartLoading
  const isSubmitting = checkout.isPending || createCharge.isPending

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!shippingAddressId) return
    setErrorMsg(null)

    // Step 1: Create order
    checkout.mutate(
      {
        shipping_address_id: shippingAddressId,
        billing_address_id: billingAddressId ?? shippingAddressId,
        payment_method: paymentMethod,
        notes: notes || undefined,
      },
      {
        onSuccess: (data) => {
          const order = data.data
          // Step 2: Create charge and redirect to Tap
          createCharge.mutate(order.id, {
            onSuccess: (chargeData) => {
              if (chargeData.redirect_url) {
                window.location.href = chargeData.redirect_url
              }
            },
            onError: (err: unknown) => {
              const error = err as { message?: string }
              setErrorMsg(error.message ?? tp("charge_error"))
            },
          })
        },
        onError: (err: unknown) => {
          const error = err as { message?: string }
          setErrorMsg(error.message ?? t("error_generic"))
        },
      },
    )
  }

  if (isLoading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        {t("placing")}
      </div>
    )
  }

  return (
    <main className="mx-auto max-w-5xl px-4 py-8">
      <h1 className="mb-6 text-2xl font-bold">{t("page_title")}</h1>

      <form onSubmit={handleSubmit}>
        <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
          {/* Left column — address + payment */}
          <div className="space-y-8">
            {/* Shipping address */}
            <section className="rounded-xl border border-border p-4 space-y-4">
              <AddressSelector
                addresses={addresses}
                selectedId={shippingAddressId}
                onSelect={setShippingAddress}
                label={t("shipping_address")}
              />
            </section>

            {/* Billing address */}
            <section className="rounded-xl border border-border p-4 space-y-4">
              <h2 className="text-sm font-semibold">{t("billing_address")}</h2>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={sameAsShipping}
                  onChange={(e) => setSameAsShipping(e.target.checked)}
                  className="accent-primary"
                />
                {t("same_as_shipping")}
              </label>
              {!sameAsShipping && (
                <AddressSelector
                  addresses={addresses}
                  selectedId={billingAddressId}
                  onSelect={setBillingAddress}
                  label={t("billing_address")}
                />
              )}
            </section>

            {/* Payment method */}
            <section className="rounded-xl border border-border p-4 space-y-3">
              <h2 className="text-sm font-semibold">{t("payment_method")}</h2>
              <ul role="list" className="space-y-2">
                {PAYMENT_METHODS.map(({ value, key }) => (
                  <li key={value}>
                    <label className="flex cursor-pointer items-center gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-muted/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                      <input
                        type="radio"
                        name="payment_method"
                        value={value}
                        checked={paymentMethod === value}
                        onChange={() => setPaymentMethod(value)}
                        className="accent-primary"
                      />
                      <span className="text-sm font-medium">{t(key)}</span>
                    </label>
                  </li>
                ))}
              </ul>
            </section>

            {/* Notes */}
            <section className="rounded-xl border border-border p-4 space-y-2">
              <label className="block space-y-1.5">
                <span className="text-sm font-semibold">{t("notes_label")}</span>
                <textarea
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  placeholder={t("notes_placeholder")}
                  rows={3}
                  className="w-full rounded-lg border border-border bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                />
              </label>
            </section>
          </div>

          {/* Right column — order summary */}
          <aside className="space-y-4 lg:sticky lg:top-4 self-start">
            <div className="rounded-xl border border-border p-4 space-y-4">
              <h2 className="font-semibold">{t("order_summary")}</h2>

              {cart && (
                <dl className="space-y-2 text-sm">
                  <div className="flex justify-between">
                    <dt className="text-muted-foreground">{t("subtotal")}</dt>
                    <dd>{formatBHD(cart.subtotal_fils, locale === "ar" ? "ar-BH" : "en-BH")}</dd>
                  </div>
                  {cart.discount_fils > 0 && (
                    <div className="flex justify-between text-green-600">
                      <dt>{t("discount")}</dt>
                      <dd>−{formatBHD(cart.discount_fils, locale === "ar" ? "ar-BH" : "en-BH")}</dd>
                    </div>
                  )}
                  <div className="flex justify-between">
                    <dt className="text-muted-foreground">{t("vat")}</dt>
                    <dd>{formatBHD(cart.vat_fils, locale === "ar" ? "ar-BH" : "en-BH")}</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-muted-foreground">{t("delivery")}</dt>
                    <dd className="text-green-600">{t("free")}</dd>
                  </div>
                  <div className="flex justify-between border-t border-border pt-2 font-semibold">
                    <dt>{t("total")}</dt>
                    <dd>{formatBHD(cart.total_fils, locale === "ar" ? "ar-BH" : "en-BH")}</dd>
                  </div>
                </dl>
              )}

              {errorMsg && (
                <p className="text-sm text-destructive">{errorMsg}</p>
              )}

              {!shippingAddressId && (
                <p className="text-xs text-destructive">{t("address_required")}</p>
              )}

              <button
                type="submit"
                disabled={isSubmitting || !shippingAddressId}
                className="w-full rounded-lg bg-primary py-3 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
              >
                {isSubmitting ? t("placing") : t("place_order")}
              </button>
            </div>
          </aside>
        </div>
      </form>
    </main>
  )
}
