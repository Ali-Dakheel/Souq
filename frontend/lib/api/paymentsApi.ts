import {
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query"
import { queryKeys } from "@/lib/query-keys"
import { useCartStore } from "@/stores/cartStore"
import type {
  ChargeResponse,
  PaymentResultResponse,
  RefundRequestInput,
  TapTransaction,
} from "@/schemas/payments"
import type { Refund } from "@/schemas/payments"

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"

// ─── Fetch helper (same as ordersApi) ────────────────────────────────────────

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

// ─── Create charge (initiate payment) ────────────────────────────────────────

export function useCreateCharge() {
  return useMutation({
    mutationFn: (orderId: number) =>
      apiFetch<ChargeResponse>("/payments/charge", {
        method: "POST",
        body: JSON.stringify({ order_id: orderId }),
      }),
  })
}

// ─── Payment result (redirect return from Tap) ──────────────────────────────

export function usePaymentResult(tapId: string | null) {
  return useQuery({
    queryKey: queryKeys.payments.result(tapId ?? ""),
    queryFn: () =>
      apiFetch<PaymentResultResponse>(`/payments/result?tap_id=${tapId}`).then(
        (r) => r.data,
      ),
    enabled: !!tapId,
    staleTime: 0, // always fresh
    retry: 2,
  })
}

// ─── Poll payment status for an order ────────────────────────────────────────

export function useOrderPayment(orderId: number | null, enabled = true) {
  return useQuery({
    queryKey: queryKeys.payments.order(orderId ?? 0),
    queryFn: () =>
      apiFetch<{ data: TapTransaction | null }>(
        `/payments/order/${orderId}`,
      ).then((r) => r.data),
    enabled: !!orderId && enabled,
    staleTime: 0,
    refetchInterval: 3000, // poll every 3s when enabled
  })
}

// ─── Request refund ──────────────────────────────────────────────────────────

export function useRequestRefund(transactionId: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (input: RefundRequestInput) =>
      apiFetch<{ data: Refund }>(`/payments/${transactionId}/refund`, {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.orders.all })
      qc.invalidateQueries({ queryKey: queryKeys.payments.all })
    },
  })
}
