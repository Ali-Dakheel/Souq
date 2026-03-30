import { z } from "zod"

// ─── Tap Transaction (payment attempt) ───────────────────────────────────────

export const TapTransactionSchema = z.object({
  id: z.number(),
  order_id: z.number(),
  tap_charge_id: z.string().nullable(),
  amount_fils: z.number(),
  currency: z.string(),
  status: z.enum(["pending", "initiated", "captured", "failed", "cancelled"]),
  payment_method: z.string().nullable(),
  attempt_number: z.number(),
  failure_reason: z.string().nullable().optional(),
  redirect_url: z.string().nullable().optional(),
  created_at: z.string().nullable(),
  updated_at: z.string().nullable(),
})

// ─── Refund ──────────────────────────────────────────────────────────────────

export const RefundSchema = z.object({
  id: z.number(),
  order_id: z.number(),
  tap_transaction_id: z.number().nullable(),
  tap_refund_id: z.string().nullable(),
  refund_amount_fils: z.number(),
  refund_reason: z.string(),
  status: z.enum([
    "pending",
    "initiated",
    "approved",
    "rejected",
    "processing",
    "completed",
    "failed",
  ]),
  customer_notes: z.string().nullable(),
  admin_notes: z.string().nullable().optional(),
  created_at: z.string().nullable(),
  processed_at: z.string().nullable(),
})

// ─── Request shapes ──────────────────────────────────────────────────────────

export const CreateChargeInputSchema = z.object({
  order_id: z.number().positive(),
})

export const RefundRequestInputSchema = z.object({
  reason: z.enum(["customer_request", "duplicate_charge", "other"]),
  notes: z.string().max(1000).optional(),
})

// ─── API response shapes ─────────────────────────────────────────────────────

export const ChargeResponseSchema = z.object({
  data: TapTransactionSchema,
  redirect_url: z.string().nullable(),
})

export const PaymentResultResponseSchema = z.object({
  data: TapTransactionSchema,
})

// ─── Inferred types ──────────────────────────────────────────────────────────

export type TapTransaction = z.infer<typeof TapTransactionSchema>
export type Refund = z.infer<typeof RefundSchema>
export type CreateChargeInput = z.infer<typeof CreateChargeInputSchema>
export type RefundRequestInput = z.infer<typeof RefundRequestInputSchema>
export type ChargeResponse = z.infer<typeof ChargeResponseSchema>
export type PaymentResultResponse = z.infer<typeof PaymentResultResponseSchema>
