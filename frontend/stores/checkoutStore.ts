import { create } from "zustand"

type PaymentMethod = "card" | "benefit" | "benefit_pay" | "apple_pay"

interface CheckoutState {
  shippingAddressId: number | null
  billingAddressId: number | null
  sameAsShipping: boolean
  paymentMethod: PaymentMethod
  notes: string
}

interface CheckoutActions {
  setShippingAddress: (id: number) => void
  setBillingAddress: (id: number) => void
  setSameAsShipping: (same: boolean) => void
  setPaymentMethod: (method: PaymentMethod) => void
  setNotes: (notes: string) => void
  reset: () => void
}

const initialState: CheckoutState = {
  shippingAddressId: null,
  billingAddressId: null,
  sameAsShipping: true,
  paymentMethod: "card",
  notes: "",
}

export const useCheckoutStore = create<CheckoutState & CheckoutActions>()(
  (set, get) => ({
    ...initialState,

    setShippingAddress(id) {
      set({ shippingAddressId: id })
      if (get().sameAsShipping) {
        set({ billingAddressId: id })
      }
    },

    setBillingAddress(id) {
      set({ billingAddressId: id, sameAsShipping: false })
    },

    setSameAsShipping(same) {
      set({ sameAsShipping: same })
      if (same) {
        set({ billingAddressId: get().shippingAddressId })
      }
    },

    setPaymentMethod(method) {
      set({ paymentMethod: method })
    },

    setNotes(notes) {
      set({ notes })
    },

    reset() {
      set(initialState)
    },
  }),
)
