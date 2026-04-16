import type { Product, ProductSku } from '~/types/product'

export interface CartItem {
  key: string // `${productId}-${skuId}`
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

export function useCart() {
  const cartStore = useCartStore()

  const cartItems = computed(() => cartStore.items as CartItem[])
  const cartCount = computed(() => cartStore.totalItems)
  const cartTotal = computed(() => cartStore.totalPrice)
  const isEmpty = computed(() => cartStore.isEmpty)

  function addToCart(product: Product, quantity = 1, sku?: ProductSku) {
    const price = sku?.price ?? product.price
    const originalPrice = sku?.original_price ?? product.original_price
    const skuId = sku?.id ?? 0
    const key = `${product.id}-${skuId}`
    const image = product.images?.[0]?.url ?? product.thumbnail ?? ''

    cartStore.addItem({
      key,
      productId: product.id,
      skuId,
      name: product.name,
      image,
      price,
      originalPrice,
      quantity,
      size: sku?.attributes?.size,
      color: sku?.attributes?.color,
    })
  }

  function removeFromCart(skuId: number) {
    cartStore.removeItem(skuId)
  }

  function updateQuantity(skuId: number, quantity: number) {
    cartStore.updateQuantity(skuId, quantity)
  }

  function clearCart() {
    cartStore.clearCart()
  }

  return {
    cartItems,
    cartCount,
    cartTotal,
    isEmpty,
    addToCart,
    removeFromCart,
    updateQuantity,
    clearCart,
  }
}
