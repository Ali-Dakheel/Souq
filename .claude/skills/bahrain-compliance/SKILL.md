---
name: bahrain-compliance
description: >
  Bahrain legal requirements for ecommerce. VAT 10%, PDPL data privacy,
  Commercial Registration, Resolution 43 electronic payments mandate.
  Auto-referenced when working on checkout, tax, cookie consent, invoicing.
---

# Bahrain Ecommerce Compliance

## VAT — 10% (mandatory)

```php
// backend/app/Modules/Orders/Services/TaxService.php
class TaxService
{
    public const VAT_RATE = 0.10;

    public function calculate(int $subtotalFils): array
    {
        $vatFils   = (int) round($subtotalFils * self::VAT_RATE);
        $totalFils = $subtotalFils + $vatFils;

        return [
            'subtotal_fils' => $subtotalFils,
            'vat_fils'      => $vatFils,
            'vat_rate'      => self::VAT_RATE,
            'total_fils'    => $totalFils,
        ];
    }
}
```

## Checkout VAT display (frontend)

```tsx
function OrderSummary({ order }: { order: Order }) {
  const { locale } = useLocale()
  const t = useTranslations('checkout')

  return (
    <div className="space-y-2">
      <div className="flex justify-between text-sm">
        <span>{t('subtotal')}</span>
        <span>{formatBHD(order.subtotal_fils, locale)}</span>
      </div>
      <div className="flex justify-between text-sm text-muted-foreground">
        <span>{t('vat', { rate: '10%' })}</span>
        <span>{formatBHD(order.vat_fils, locale)}</span>
      </div>
      <div className="flex justify-between font-semibold border-t pt-2">
        <span>{t('total')}</span>
        <span>{formatBHD(order.total_fils, locale)}</span>
      </div>
      <p className="text-xs text-muted-foreground">{t('vat_inclusive')}</p>
    </div>
  )
}
```

## Invoice requirements (NBR-compatible)

Every order confirmation email/PDF must include:
- Seller's CR number
- Seller's VAT registration number
- Buyer's name and address
- Invoice date and sequential invoice number
- Itemized products with unit price (exc. VAT) and VAT amount
- Subtotal (exc. VAT), VAT amount, total (inc. VAT)
- All amounts in BHD with 3 decimal places

## PDPL — Cookie consent before analytics

```tsx
// frontend/src/components/CookieConsent.tsx
'use client'

import { useEffect, useState } from 'react'
import { setCookie, getCookie } from 'cookies-next'

export function CookieConsent() {
  const [show, setShow] = useState(false)

  useEffect(() => {
    if (!getCookie('cookie-consent')) setShow(true)
  }, [])

  const accept = (level: 'all' | 'essential') => {
    setCookie('cookie-consent', level, { maxAge: 365 * 24 * 60 * 60 })
    if (level === 'all') {
      // Only NOW initialize analytics
      window.dataLayer?.push({ event: 'consent_granted' })
    }
    setShow(false)
  }

  if (!show) return null
  return (
    <div className="fixed bottom-0 start-0 end-0 z-50 p-4 bg-background border-t">
      {/* Bilingual AR/EN cookie notice */}
    </div>
  )
}
```

## Commercial Registration checklist

Before launching any client site:
- [ ] CR via Sijilat (sijilat.bh) — ISIC code 4791
- [ ] Returns/refund policy published (min 7 days)
- [ ] Privacy policy with PDPL compliance
- [ ] SSL certificate active
- [ ] Electronic payment integrated (Resolution No. 43 — legally required)
- [ ] Delivery timeframes stated on product pages
- [ ] Contact information in footer
- [ ] CR number in footer
- [ ] VAT number in footer (if registered)
- [ ] Payment method logos at checkout

## Required site pages

```
/privacy-policy     ← PDPL compliance, cookie policy
/returns-policy     ← Consumer protection (7-day minimum)
/terms-of-service   ← Ecommerce terms
/about              ← CR number, contact details
/contact            ← Phone, email, WhatsApp (standard in Bahrain)
```

## Resolution No. 43 (2024)

All commercial establishments must:
1. Open a commercial bank account
2. Accept at least one CBB-approved electronic payment method
3. Display payment method logos prominently

Tap Payments covers this requirement entirely.
Phase 1 (Dec 2024): new businesses. Phase 2 (Jun 2025): all businesses.
This is a legal requirement — not optional.
