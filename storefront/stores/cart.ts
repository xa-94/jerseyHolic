import { defineStore } from 'pinia'

interface CartItem {
  key: string
  productId: number
  skuId: number
  name: string
  image: string
  price: number
  originalPrice?: number
  quantity: number
  size?: string
  color?: string
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [] as CartItem[],
  }),

  getters: {
    totalItems: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),
    totalPrice: (state) => state.items.reduce((sum, item) => sum + item.price * item.quantity, 0),
    isEmpty: (state) => state.items.length === 0,
  },

  actions: {
    addItem(item: CartItem) {
      const existing = this.items.find(i => i.key === item.key)
      if (existing) {
        existing.quantity += item.quantity
      } else {
        this.items.push({ ...item })
      }
    },

    removeItem(skuId: number) {
      this.items = this.items.filter(i => i.skuId !== skuId)
    },

    updateQuantity(skuId: number, quantity: number) {
      const item = this.items.find(i => i.skuId === skuId)
      if (item) {
        item.quantity = Math.max(1, quantity)
      }
    },

    clearCart() {
      this.items = []
    },
  },

  persist: true,
})
