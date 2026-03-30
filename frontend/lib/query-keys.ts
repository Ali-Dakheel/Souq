export const queryKeys = {
  cart: {
    all: ["cart"] as const,
    detail: (sessionId?: string) =>
      ["cart", "detail", sessionId ?? "auth"] as const,
  },
  coupons: {
    active: (categoryId?: number, productId?: number) =>
      ["coupons", "active", { categoryId, productId }] as const,
    validate: (code: string) => ["coupons", "validate", code] as const,
  },
} as const
