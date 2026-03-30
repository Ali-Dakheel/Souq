import {
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query"
import { queryKeys } from "@/lib/query-keys"
import { useCartStore } from "@/stores/cartStore"
import type {
  AddToCartInput,
  Cart,
  UpdateCartItemInput,
} from "@/schemas/cart"

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"

// ─── Fetch helpers ────────────────────────────────────────────────────────────

function getSessionId() {
  return useCartStore.getState().sessionId
}

async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const sessionId = getSessionId()
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...(options.headers as Record<string, string>),
  }
  if (sessionId) {
    headers["X-Cart-Session"] = sessionId
  }

  const res = await fetch(`${BASE_URL}/api/v1${path}`, {
    ...options,
    credentials: "include",
    headers,
  })

  if (!res.ok) {
    const body = await res.json().catch(() => ({}))
    throw Object.assign(new Error(body?.message ?? "Request failed"), {
      status: res.status,
      errors: body?.errors ?? {},
    })
  }

  return res.json() as Promise<T>
}

// ─── Hooks ────────────────────────────────────────────────────────────────────

export function useCart() {
  const sessionId = useCartStore((s) => s.sessionId)

  return useQuery({
    queryKey: queryKeys.cart.detail(sessionId ?? undefined),
    queryFn: () =>
      apiFetch<{ data: Cart }>("/cart").then((r) => r.data),
    staleTime: 30_000,
  })
}

export function useAddToCart() {
  const qc = useQueryClient()
  const { sessionId, setItemCount } = useCartStore()

  return useMutation({
    mutationFn: (input: AddToCartInput) =>
      apiFetch<{ cart: Cart }>("/cart/add-item", {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: (data) => {
      setItemCount(data.cart.item_count)
      qc.setQueryData(
        queryKeys.cart.detail(sessionId ?? undefined),
        data.cart,
      )
    },
  })
}

export function useUpdateCartItem() {
  const qc = useQueryClient()
  const { sessionId, setItemCount } = useCartStore()

  return useMutation({
    mutationFn: ({
      itemId,
      input,
    }: {
      itemId: number
      input: UpdateCartItemInput
    }) =>
      apiFetch<{ data: { quantity: number }; cart: Cart }>(
        `/cart/items/${itemId}`,
        {
          method: "PUT",
          body: JSON.stringify(input),
        },
      ),
    onSuccess: (data) => {
      setItemCount(data.cart.item_count)
      qc.setQueryData(
        queryKeys.cart.detail(sessionId ?? undefined),
        data.cart,
      )
    },
  })
}

export function useRemoveCartItem() {
  const qc = useQueryClient()
  const { sessionId, setItemCount } = useCartStore()

  return useMutation({
    mutationFn: (itemId: number) =>
      apiFetch<{ cart: Cart }>(`/cart/items/${itemId}`, {
        method: "DELETE",
      }),
    onSuccess: (data) => {
      setItemCount(data.cart.item_count)
      qc.setQueryData(
        queryKeys.cart.detail(sessionId ?? undefined),
        data.cart,
      )
    },
  })
}

export function useApplyCoupon() {
  const qc = useQueryClient()
  const sessionId = useCartStore((s) => s.sessionId)

  return useMutation({
    mutationFn: (couponCode: string) =>
      apiFetch<{ data: Cart }>("/cart/apply-coupon", {
        method: "POST",
        body: JSON.stringify({ coupon_code: couponCode }),
      }),
    onSuccess: (data) => {
      qc.setQueryData(
        queryKeys.cart.detail(sessionId ?? undefined),
        data.data,
      )
    },
  })
}

export function useRemoveCoupon() {
  const qc = useQueryClient()
  const sessionId = useCartStore((s) => s.sessionId)

  return useMutation({
    mutationFn: () =>
      apiFetch<{ data: Cart }>("/cart/remove-coupon", { method: "POST" }),
    onSuccess: (data) => {
      qc.setQueryData(
        queryKeys.cart.detail(sessionId ?? undefined),
        data.data,
      )
    },
  })
}

export function useClearCart() {
  const qc = useQueryClient()
  const { sessionId, setItemCount } = useCartStore()

  return useMutation({
    mutationFn: () =>
      apiFetch<{ message: string }>("/cart/clear", { method: "POST" }),
    onSuccess: () => {
      setItemCount(0)
      qc.setQueryData(queryKeys.cart.detail(sessionId ?? undefined), {
        item_count: 0,
        subtotal_fils: 0,
        discount_fils: 0,
        vat_fils: 0,
        total_fils: 0,
        coupon_code: null,
        items: [],
      } satisfies Cart)
    },
  })
}

export function useMergeCart() {
  const qc = useQueryClient()
  const { sessionId, setItemCount, clearSession } = useCartStore()

  return useMutation({
    mutationFn: (guestSessionId: string) =>
      apiFetch<{ message: string; items_added: number; cart?: Cart }>(
        "/cart/merge",
        {
          method: "POST",
          body: JSON.stringify({ guest_session_id: guestSessionId }),
        },
      ),
    onSuccess: (data) => {
      clearSession()
      if (data.cart) {
        setItemCount(data.cart.item_count)
        qc.setQueryData(queryKeys.cart.detail(undefined), data.cart)
      } else {
        qc.invalidateQueries({ queryKey: queryKeys.cart.all })
      }
    },
  })
}
