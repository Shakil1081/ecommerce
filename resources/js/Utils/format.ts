/**
 * Display-only money formatting. Amounts arrive from the server as DECIMAL(14,4) strings;
 * they are never used for arithmetic on the client.
 */
export function formatBdt(amount: string | number | null | undefined): string {
  const value = Number(amount ?? 0);
  return `৳${value.toLocaleString('en-BD', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

const ORDER_STATUS_LABELS: Record<string, string> = {
  pending: 'Order placed',
  confirmed: 'Confirmed',
  processing: 'Processing',
  picked_and_packed: 'Packed',
  handed_to_courier: 'Shipped',
  out_for_delivery: 'Out for delivery',
  delivered: 'Delivered',
  returned: 'Returned',
  cancelled: 'Cancelled',
};

export function orderStatusLabel(status: string): string {
  return ORDER_STATUS_LABELS[status] ?? status.replace(/_/g, ' ');
}

const PAYMENT_METHOD_LABELS: Record<string, string> = {
  cash_on_delivery: 'Cash on Delivery',
  bkash: 'bKash',
  nagad: 'Nagad',
  sslcommerz: 'Card / Online',
};

export function paymentMethodLabel(method: string): string {
  return PAYMENT_METHOD_LABELS[method] ?? method;
}

const RETURN_REASON_LABELS: Record<string, string> = {
  defective: 'Product is defective',
  wrong_item: 'Received the wrong item',
  damaged_in_shipping: 'Damaged in delivery',
  changed_mind: 'Changed my mind',
};

export function returnReasonLabel(reason: string): string {
  return RETURN_REASON_LABELS[reason] ?? reason.replace(/_/g, ' ');
}
