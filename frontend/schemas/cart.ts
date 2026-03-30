import { z } from "zod"

// ─── Response shapes ────────────────────────────────────────────────────────

export const CartItemSchema = z.object({
  id: z.number(),
  variant_id: z.number(),
  sku: z.string(),
  product_name: z.string(),
  variant_label: z.string().nullable(),
  quantity: z.number(),
  price_fils_snapshot: z.number(),
  current_price_fils: z.number(),
  price_changed: z.boolean(),
  line_total_fils: z.number(),
})

export const CartSchema = z.object({
  item_count: z.number(),
  subtotal_fils: z.number(),
  discount_fils: z.number(),
  vat_fils: z.number(),
  total_fils: z.number(),
  coupon_code: z.string().nullable(),
  items: z.array(CartItemSchema),
})

export const CartResponseSchema = z.object({
  data: CartSchema,
})

// ─── Request shapes ──────────────────────────────────────────────────────────

export const AddToCartSchema = z.object({
  variant_id: z.number().positive(),
  quantity: z.number().int().min(1).max(10),
})

export const UpdateCartItemSchema = z.object({
  quantity: z.number().int().min(1).max(10),
})

export const ApplyCouponSchema = z.object({
  coupon_code: z.string().min(1).max(50),
})

export const MergeCartSchema = z.object({
  guest_session_id: z.string().min(1),
})

// ─── Inferred types ──────────────────────────────────────────────────────────

export type CartItem = z.infer<typeof CartItemSchema>
export type Cart = z.infer<typeof CartSchema>
export type AddToCartInput = z.infer<typeof AddToCartSchema>
export type UpdateCartItemInput = z.infer<typeof UpdateCartItemSchema>
export type ApplyCouponInput = z.infer<typeof ApplyCouponSchema>
export type MergeCartInput = z.infer<typeof MergeCartSchema>
