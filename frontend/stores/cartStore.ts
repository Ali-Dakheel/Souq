import { create } from "zustand"
import { persist, createJSONStorage } from "zustand/middleware"

interface CartState {
  /** Guest cart session UUID. Null when user is authenticated. */
  sessionId: string | null
  /** Optimistic item count shown in header badge. */
  itemCount: number
  /** Whether the cart drawer is open. */
  drawerOpen: boolean
}

interface CartActions {
  /** Ensure a session ID exists (creates one if absent). Returns it. */
  ensureSession: () => string
  /** Clear the guest session after merge/login. */
  clearSession: () => void
  /** Update the item count from a server response. */
  setItemCount: (count: number) => void
  /** Optimistically increment item count. */
  incrementCount: (by?: number) => void
  /** Optimistically decrement item count. */
  decrementCount: (by?: number) => void
  openDrawer: () => void
  closeDrawer: () => void
  toggleDrawer: () => void
}

function generateSessionId(): string {
  // crypto.randomUUID is available in all modern browsers and Node 15+
  if (typeof crypto !== "undefined" && crypto.randomUUID) {
    return crypto.randomUUID()
  }
  // Fallback for older environments
  return Math.random().toString(36).slice(2) + Date.now().toString(36)
}

export const useCartStore = create<CartState & CartActions>()(
  persist(
    (set, get) => ({
      sessionId: null,
      itemCount: 0,
      drawerOpen: false,

      ensureSession() {
        const existing = get().sessionId
        if (existing) return existing
        const id = generateSessionId()
        set({ sessionId: id })
        return id
      },

      clearSession() {
        set({ sessionId: null })
      },

      setItemCount(count) {
        set({ itemCount: count })
      },

      incrementCount(by = 1) {
        set((s) => ({ itemCount: s.itemCount + by }))
      },

      decrementCount(by = 1) {
        set((s) => ({ itemCount: Math.max(0, s.itemCount - by) }))
      },

      openDrawer() {
        set({ drawerOpen: true })
      },

      closeDrawer() {
        set({ drawerOpen: false })
      },

      toggleDrawer() {
        set((s) => ({ drawerOpen: !s.drawerOpen }))
      },
    }),
    {
      name: "souq-cart",
      storage: createJSONStorage(() => localStorage),
      // Only persist session identity and count — drawer state is ephemeral
      partialize: (s) => ({
        sessionId: s.sessionId,
        itemCount: s.itemCount,
      }),
    },
  ),
)
