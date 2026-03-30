import { z } from "zod"

// ─── Address snapshot (stored on order at checkout time) ─────────────────────

export const AddressSnapshotSchema = z.object({
  recipient_name: z.string(),
  phone: z.string(),
  governorate: z.string(),
  district: z.string(),
  street_address: z.string(),
  building_number: z.string().nullable(),
  apartment_number: z.string().nullable(),
  postal_code: z.string().nullable(),
  delivery_instructions: z.string().nullable(),
})

// ─── Customer address (from /customers/addresses) ────────────────────────────

export const CustomerAddressSchema = z.object({
  id: z.number(),
  address_type: z.enum(["shipping", "billing", "both"]),
  recipient_name: z.string(),
  phone: z.string(),
  governorate: z.string(),
  district: z.string(),
  street_address: z.string(),
  building_number: z.string().nullable(),
  apartment_number: z.string().nullable(),
  postal_code: z.string().nullable(),
  delivery_instructions: z.string().nullable(),
  is_default: z.boolean(),
  is_active: z.boolean(),
  created_at: z.string().nullable(),
})

// ─── Order item snapshot ──────────────────────────────────────────────────────

export const OrderItemSchema = z.object({
  sku: z.string(),
  product_name: z.union([
    z.object({ ar: z.string(), en: z.string() }),
    z.string(),
  ]),
  variant_attributes: z.record(z.string(), z.string()).nullable(),
  quantity: z.number(),
  price_fils_per_unit: z.number(),
  total_fils: z.number(),
})

// ─── Status history entry ─────────────────────────────────────────────────────

export const OrderStatusHistorySchema = z.object({
  old_status: z.string().nullable(),
  new_status: z.string(),
  changed_by: z.string().nullable(),
  reason: z.string().nullable(),
  created_at: z.string().nullable(),
})

// ─── Order (full detail) ──────────────────────────────────────────────────────

export const OrderSchema = z.object({
  id: z.number(),
  order_number: z.string(),
  order_status: z.enum([
    "pending",
    "initiated",
    "paid",
    "failed",
    "cancelled",
    "refunded",
    "fulfilled",
  ]),
  payment_method: z.string(),
  subtotal_fils: z.number(),
  coupon_code: z.string().nullable(),
  coupon_discount_fils: z.number(),
  vat_fils: z.number(),
  delivery_fee_fils: z.number(),
  total_fils: z.number(),
  notes: z.string().nullable(),
  paid_at: z.string().nullable(),
  cancelled_at: z.string().nullable(),
  created_at: z.string(),
  items: z.array(OrderItemSchema).optional(),
  status_history: z.array(OrderStatusHistorySchema).optional(),
  shipping_address: AddressSnapshotSchema.nullable().optional(),
  billing_address: AddressSnapshotSchema.nullable().optional(),
})

// ─── Order list item (paginated index) ───────────────────────────────────────

export const OrderListItemSchema = z.object({
  id: z.number(),
  order_number: z.string(),
  order_status: OrderSchema.shape.order_status,
  total_fils: z.number(),
  item_count: z.number(),
  created_at: z.string(),
})

// ─── Paginated response ───────────────────────────────────────────────────────

export const OrdersResponseSchema = z.object({
  data: z.array(OrderListItemSchema),
  meta: z.object({
    current_page: z.number(),
    last_page: z.number(),
    per_page: z.number(),
    total: z.number(),
  }),
})

// ─── Request shapes ───────────────────────────────────────────────────────────

export const CheckoutInputSchema = z.object({
  shipping_address_id: z.number().positive(),
  billing_address_id: z.number().positive(),
  payment_method: z.enum(["card", "benefit", "benefit_pay", "apple_pay"]),
  notes: z.string().max(500).optional(),
})

export const CancelOrderInputSchema = z.object({
  reason: z.string().max(500).optional(),
})

// ─── Inferred types ───────────────────────────────────────────────────────────

export type AddressSnapshot = z.infer<typeof AddressSnapshotSchema>
export type CustomerAddress = z.infer<typeof CustomerAddressSchema>
export type OrderItem = z.infer<typeof OrderItemSchema>
export type OrderStatusHistory = z.infer<typeof OrderStatusHistorySchema>
export type Order = z.infer<typeof OrderSchema>
export type OrderListItem = z.infer<typeof OrderListItemSchema>
export type CheckoutInput = z.infer<typeof CheckoutInputSchema>
export type CancelOrderInput = z.infer<typeof CancelOrderInputSchema>
