---
name: nextjs-rtl
description: >
  Next.js 16 + next-intl + shadcn/ui RTL patterns. Locale routing, direction
  switching, Arabic typography, BHD formatting, Zustand + TanStack Query.
  Auto-referenced when working in frontend/.
---

# Next.js RTL Ecommerce Patterns

## Root layout — direction switching

```tsx
// frontend/src/app/[locale]/layout.tsx
import { IBM_Plex_Sans_Arabic } from 'next/font/google'
import { NextIntlClientProvider } from 'next-intl'
import { getMessages } from 'next-intl/server'

const arabic = IBM_Plex_Sans_Arabic({
  weight: ['400', '500', '600', '700'],
  subsets: ['arabic'],
  variable: '--font-arabic',
  display: 'swap',
})

export default async function LocaleLayout({
  children,
  params: { locale },
}: {
  children: React.ReactNode
  params: { locale: string }
}) {
  const messages = await getMessages()

  return (
    <html
      lang={locale}
      dir={locale === 'ar' ? 'rtl' : 'ltr'}
      className={arabic.variable}
    >
      <body>
        <NextIntlClientProvider messages={messages}>
          {children}
        </NextIntlClientProvider>
      </body>
    </html>
  )
}
```

## BHD formatting — use everywhere

```tsx
// frontend/src/lib/currency.ts
export function formatBHD(fils: number, locale: string = 'en-BH'): string {
  return new Intl.NumberFormat(locale === 'ar' ? 'ar-BH' : 'en-BH', {
    style: 'currency',
    currency: 'BHD',
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  }).format(fils / 1000)
}
// formatBHD(10500, 'ar') → "١٠٫٥٠٠ د.ب.‏"
// formatBHD(10500, 'en') → "BHD 10.500"
```

## RTL-safe component patterns

```tsx
// ✅ Correct — logical properties
<div className="ms-4 ps-3 text-start border-s rounded-s-md">

// ❌ Wrong — directional (breaks in RTL)
<div className="ml-4 pl-3 text-left border-l rounded-l-md">

// ✅ Icons that represent direction must flip
<ChevronRight className="h-4 w-4 rtl:rotate-180" />
<ArrowLeft className="h-4 w-4 rtl:-scale-x-100" />
```

## Query key factory — single source of truth

```ts
// frontend/src/lib/query-keys.ts
export const productKeys = {
  all:    ['products'] as const,
  list:   (filters: ProductFilters) => ['products', 'list', filters] as const,
  detail: (slug: string) => ['products', 'detail', slug] as const,
}

export const inventoryKeys = {
  stock: (productId: string) => ['inventory', productId] as const,
}

export const cartKeys = {
  detail: () => ['cart'] as const,
}

export const orderKeys = {
  all:    ['orders'] as const,
  detail: (id: string) => ['orders', id] as const,
}
```

## staleTime rules

```ts
export const STALE = {
  PRODUCTS:   5 * 60 * 1000,   // 5 min
  CATEGORIES: 10 * 60 * 1000,  // 10 min
  INVENTORY:  0,                // always fresh
  CART:       0,                // always fresh
  ORDERS:     0,                // always fresh
} as const
```

## SSR hydration for product pages

```tsx
// Server Component prefetches, client gets instant data (no loading flash)
import { HydrationBoundary, dehydrate } from '@tanstack/react-query'
import { getQueryClient } from '@/lib/query-client'

export default async function ProductPage({ params }: { params: { slug: string } }) {
  const queryClient = getQueryClient()

  await queryClient.prefetchQuery({
    queryKey: productKeys.detail(params.slug),
    queryFn: () => api.getProduct(params.slug),
  })

  return (
    <HydrationBoundary state={dehydrate(queryClient)}>
      <ProductDetails slug={params.slug} />
    </HydrationBoundary>
  )
}
```

## Zustand cart store with Zod validation

```ts
// frontend/src/stores/cart.ts
import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { z } from 'zod'

const CartItemSchema = z.object({
  productId: z.string(),
  variantId: z.string(),
  priceFils: z.number().int().positive(),
  quantity:  z.number().int().positive(),
  name:      z.object({ en: z.string(), ar: z.string() }),
})

const CartStateSchema = z.object({
  items: z.array(CartItemSchema),
})

export const useCartStore = create<CartStore>()(
  persist(
    (set, get) => ({
      items: [],
      addItem: (item) => set((state) => {
        const existing = state.items.find(i => i.variantId === item.variantId)
        if (existing) {
          return {
            items: state.items.map(i =>
              i.variantId === item.variantId
                ? { ...i, quantity: i.quantity + item.quantity }
                : i
            ),
          }
        }
        return { items: [...state.items, item] }
      }),
      removeItem:     (variantId) => set(state => ({
        items: state.items.filter(i => i.variantId !== variantId),
      })),
      clearCart:      () => set({ items: [] }),
      totalFils:      () => get().items.reduce((s, i) => s + i.priceFils * i.quantity, 0),
      itemCount:      () => get().items.reduce((s, i) => s + i.quantity, 0),
    }),
    {
      name: 'cart-storage',
      version: 2,
      // Validate on rehydration — corrupt data cleared gracefully
      onRehydrateStorage: () => (state) => {
        const result = CartStateSchema.safeParse(state)
        if (!result.success) return { items: [] }
      },
    }
  )
)
```

## ISR configuration for product pages

```tsx
// Product listing — 5 min
export const revalidate = 300

// Product detail — 10 min
export const revalidate = 600

// On-demand revalidation (called from Laravel after product update)
// frontend/src/app/api/revalidate/route.ts
import { revalidateTag } from 'next/cache'
export async function POST(request: Request) {
  const { tag } = await request.json()
  revalidateTag(tag) // e.g. 'products', 'product-bakery-cake'
  return Response.json({ revalidated: true })
}
```
