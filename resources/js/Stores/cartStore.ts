import { defineStore } from 'pinia';
import axios from 'axios';

export interface CartItemData {
  id: number;
  product_variant_id: string;
  product_name: string;
  sku: string;
  attributes: Record<string, string>;
  quantity: number;
  unit_price_bdt: string;
  tier_savings_bdt: string;
  subtotal_bdt: string;
  image_url: string | null;
}

export interface FreeShippingData {
  threshold_bdt: string;
  amount_remaining_bdt: string;
  is_unlocked: boolean;
  progress_percent: number;
}

export interface CartData {
  id: number;
  ulid: string;
  guest_token: string | null;
  status: string;
  coupon_code: string | null;
  total_items_count: number;
  subtotal_bdt: string;
  discount_amount_bdt: string;
  total_bdt: string;
  free_shipping: FreeShippingData;
  items: CartItemData[];
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    isOpen: false,
    isLoading: false,
    cart: null as CartData | null,
  }),

  getters: {
    totalItemsCount: (state): number => state.cart?.total_items_count ?? 0,
    subtotal: (state): number => Number(state.cart?.subtotal_bdt ?? 0),
    total: (state): number => Number(state.cart?.total_bdt ?? 0),
    items: (state): CartItemData[] => state.cart?.items ?? [],
    freeShipping: (state): FreeShippingData =>
      state.cart?.free_shipping ?? {
        threshold_bdt: '2000.0000',
        amount_remaining_bdt: '2000.0000',
        is_unlocked: false,
        progress_percent: 0,
      },
  },

  actions: {
    openDrawer() {
      this.isOpen = true;
      if (!this.cart) {
        this.fetchCart();
      }
    },

    closeDrawer() {
      this.isOpen = false;
    },

    toggleDrawer() {
      if (this.isOpen) {
        this.closeDrawer();
      } else {
        this.openDrawer();
      }
    },

    async fetchCart() {
      this.isLoading = true;
      try {
        const response = await axios.get<{ data: CartData }>('/cart/drawer');
        this.cart = response.data.data;
      } catch (err) {
        console.error('Failed to load cart', err);
      } finally {
        this.isLoading = false;
      }
    },

    async addItem(productVariantId: string, quantity = 1) {
      this.isLoading = true;
      try {
        const response = await axios.post<{ data: CartData }>('/cart/items', {
          product_variant_id: productVariantId,
          quantity,
        });
        this.cart = response.data.data;
        this.openDrawer();
      } catch (err) {
        console.error('Failed to add item to cart', err);
      } finally {
        this.isLoading = false;
      }
    },

    async updateQuantity(productVariantId: string, quantity: number) {
      this.isLoading = true;
      try {
        const response = await axios.patch<{ data: CartData }>(`/cart/items/${productVariantId}`, {
          quantity,
        });
        this.cart = response.data.data;
      } catch (err) {
        console.error('Failed to update cart item quantity', err);
      } finally {
        this.isLoading = false;
      }
    },

    async removeItem(productVariantId: string) {
      this.isLoading = true;
      try {
        const response = await axios.delete<{ data: CartData }>(`/cart/items/${productVariantId}`);
        this.cart = response.data.data;
      } catch (err) {
        console.error('Failed to remove cart item', err);
      } finally {
        this.isLoading = false;
      }
    },

    async applyCoupon(code: string): Promise<{ success: boolean; message?: string }> {
      this.isLoading = true;
      try {
        const response = await axios.post<{ data: CartData }>('/cart/coupon', { code });
        this.cart = response.data.data;
        return { success: true };
      } catch (err: unknown) {
        let message = 'Invalid promotional coupon code.';
        if (axios.isAxiosError(err) && err.response?.data) {
          const data = err.response.data as { message?: string; errors?: Record<string, string[]> };
          message = data.errors?.code?.[0] || data.message || message;
        }
        return { success: false, message };
      } finally {
        this.isLoading = false;
      }
    },

    async removeCoupon(): Promise<void> {
      this.isLoading = true;
      try {
        const response = await axios.delete<{ data: CartData }>('/cart/coupon');
        this.cart = response.data.data;
      } catch (err) {
        console.error('Failed to remove coupon', err);
      } finally {
        this.isLoading = false;
      }
    },
  },
});
