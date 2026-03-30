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
  orders: {
    all: ["orders"] as const,
    list: (page?: number, status?: string) =>
      ["orders", "list", { page, status }] as const,
    detail: (orderNumber: string) => ["orders", "detail", orderNumber] as const,
  },
  addresses: {
    all: ["addresses"] as const,
  },
  payments: {
    all: ["payments"] as const,
    result: (tapId: string) => ["payments", "result", tapId] as const,
    order: (orderId: number) => ["payments", "order", orderId] as const,
  },
} as const
