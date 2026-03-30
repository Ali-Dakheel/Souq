"use client"

import { useTranslations, useLocale } from "next-intl"
import Link from "next/link"
import type { CustomerAddress } from "@/schemas/orders"

interface Props {
  addresses: CustomerAddress[]
  selectedId: number | null
  onSelect: (id: number) => void
  label: string
}

export function AddressSelector({ addresses, selectedId, onSelect, label }: Props) {
  const t = useTranslations("checkout")
  const locale = useLocale()
  const active = addresses.filter((a) => a.is_active)

  return (
    <fieldset>
      <legend className="mb-3 text-sm font-semibold">{label}</legend>

      {active.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          {t("no_addresses")}{" "}
          <Link
            href={`/${locale}/account/addresses`}
            className="text-primary underline-offset-4 hover:underline"
          >
            {t("add_address")}
          </Link>
        </p>
      ) : (
        <ul role="list" className="space-y-2">
          {active.map((addr) => (
            <li key={addr.id}>
              <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-muted/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input
                  type="radio"
                  name={label}
                  value={addr.id}
                  checked={selectedId === addr.id}
                  onChange={() => onSelect(addr.id)}
                  className="mt-0.5 accent-primary"
                />
                <div className="text-sm leading-snug">
                  <p className="font-medium">{addr.recipient_name}</p>
                  <p className="text-muted-foreground">
                    {addr.street_address}
                    {addr.building_number ? `, ${addr.building_number}` : ""}
                    {addr.apartment_number ? ` / ${addr.apartment_number}` : ""}
                  </p>
                  <p className="text-muted-foreground">
                    {addr.district}, {addr.governorate}
                  </p>
                  <p className="text-muted-foreground">{addr.phone}</p>
                </div>
              </label>
            </li>
          ))}
        </ul>
      )}
    </fieldset>
  )
}
