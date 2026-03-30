import {
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query"
import { queryKeys } from "@/lib/query-keys"
import { useCartStore } from "@/stores/cartStore"
import { useRouter } from "next/navigation"
import type {
  CheckoutInput,
  CancelOrderInput,
  Order,
  OrderListItem,
  CustomerAddress,
} from "@/schemas/orders"

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"

// ─── Fetch helper ─────────────────────────────────────────────────────────────

async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const sessionId = useCartStore.getState().sessionId
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

// ─── Addresses ────────────────────────────────────────────────────────────────

export function useAddresses() {
  return useQuery({
    queryKey: queryKeys.addresses.all,
    queryFn: () =>
      apiFetch<{ data: CustomerAddress[] }>("/customers/addresses").then(
        (r) => r.data,
      ),
    staleTime: 60_000,
  })
}

// ─── Checkout ─────────────────────────────────────────────────────────────────

export function useCheckout(locale: string) {
  const qc = useQueryClient()
  const router = useRouter()

  return useMutation({
    mutationFn: (input: CheckoutInput) =>
      apiFetch<{ data: Order }>("/checkout", {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: (data) => {
      const order = data.data
      // Seed detail cache so the order page loads instantly
      qc.setQueryData(queryKeys.orders.detail(order.order_number), order)
      // Invalidate order list so it refreshes
      qc.invalidateQueries({ queryKey: queryKeys.orders.all })
      router.push(`/${locale}/account/orders/${order.order_number}`)
    },
  })
}

// ─── Order list ───────────────────────────────────────────────────────────────

export function useOrders(page = 1, status?: string) {
  return useQuery({
    queryKey: queryKeys.orders.list(page, status),
    queryFn: () =>
      apiFetch<{
        data: OrderListItem[]
        meta: { current_page: number; last_page: number; per_page: number; total: number }
      }>(`/orders?page=${page}${status ? `&status=${status}` : ""}`),
    staleTime: 30_000,
  })
}

// ─── Order detail ─────────────────────────────────────────────────────────────

export function useOrder(orderNumber: string) {
  return useQuery({
    queryKey: queryKeys.orders.detail(orderNumber),
    queryFn: () =>
      apiFetch<{ data: Order }>(`/orders/${orderNumber}`).then((r) => r.data),
    staleTime: 60_000,
    enabled: !!orderNumber,
  })
}

// ─── Cancel order ─────────────────────────────────────────────────────────────

export function useCancelOrder(orderNumber: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (input: CancelOrderInput) =>
      apiFetch<{ data: Order }>(`/orders/${orderNumber}/cancel`, {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: (data) => {
      qc.setQueryData(queryKeys.orders.detail(orderNumber), data.data)
      qc.invalidateQueries({ queryKey: queryKeys.orders.all })
    },
  })
}
