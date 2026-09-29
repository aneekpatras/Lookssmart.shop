import * as React from 'react';

import { formatCurrency } from '@/lib/currency';

export interface ReceiptItem {
  description: string;
  quantity: number;
  unit_price: string;
  discount: string;
  total: string;
  staff: string | null;
}

export interface ReceiptPayment {
  method: string;
  amount: string;
  tendered_amount: string | null;
  change: string | null;
  reference: string | null;
  status?: string;
}

export interface ReceiptBusiness {
  name: string;
  address: string | null;
  phone: string | null;
}

export interface ReceiptSale {
  id: number;
  sale_number: string;
  customer_name: string | null;
  created_by: string | null;
  subtotal: string;
  discount: string;
  discount_percent: string | null;
  tax: string;
  tax_rate_percent: string | null;
  total: string;
  status: string;
  created_at: string | null;
  business?: ReceiptBusiness;
  items: ReceiptItem[];
  payments: ReceiptPayment[];
}

const METHOD_LABELS: Record<string, string> = {
  cash: 'Cash',
  card: 'Card',
  loyalty_points: 'Loyalty Points',
};

const FOOTER_CREDIT = 'Software developed by Aneek | 03199154505';

/**
 * The single source of truth for "what a receipt/invoice looks like" — used by both the
 * post-checkout ReceiptDialog modal and the standalone Invoice.tsx page, so the two never drift.
 * `id="pos-receipt-body"` is what the 80mm print stylesheet (Receipt.tsx/Invoice.tsx's own
 * `<style>` block) scopes down to for `PRINT THERMAL`; on-screen it just renders as a normal
 * A4-ish document.
 */
export function ReceiptBody({ sale }: { sale: ReceiptSale }) {
  return (
    <div id="pos-receipt-body" className="space-y-4">
      <div className="text-center">
        {sale.business?.name && <h2 className="font-display text-lg font-medium">{sale.business.name}</h2>}
        {sale.business?.address && <p className="text-ink-muted text-xs">{sale.business.address}</p>}
        {sale.business?.phone && <p className="text-ink-muted text-xs">{sale.business.phone}</p>}
      </div>

      <div className="text-center">
        <p className="text-sm font-medium">Invoice {sale.sale_number}</p>
        <p className="text-ink-muted text-xs">{sale.created_at ? new Date(sale.created_at).toLocaleString() : ''}</p>
        <p className="text-ink-muted text-xs">
          Served by {sale.created_by ?? 'staff'}
          {sale.customer_name ? ` — ${sale.customer_name}` : ' — Walk-in Customer'}
        </p>
      </div>

      <table className="w-full text-sm">
        <tbody>
          {sale.items.map((item, index) => (
            <tr key={index}>
              <td className="py-1">
                {item.description} &times; {item.quantity}
                {item.staff ? <span className="text-ink-muted block text-xs">by {item.staff}</span> : null}
              </td>
              <td className="py-1 text-right align-top">{formatCurrency(Number(item.total))}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <table className="w-full text-sm">
        <tbody>
          <tr>
            <td className="py-1">Subtotal</td>
            <td className="py-1 text-right">{formatCurrency(Number(sale.subtotal))}</td>
          </tr>
          {Number(sale.discount) > 0 && (
            <tr>
              <td className="py-1">
                Discount{sale.discount_percent && Number(sale.discount_percent) > 0 ? ` (${sale.discount_percent}%)` : ''}
              </td>
              <td className="py-1 text-right">-{formatCurrency(Number(sale.discount))}</td>
            </tr>
          )}
          <tr>
            <td className="py-1">
              Tax{sale.tax_rate_percent && Number(sale.tax_rate_percent) > 0 ? ` (${sale.tax_rate_percent}%)` : ''}
            </td>
            <td className="py-1 text-right">{formatCurrency(Number(sale.tax))}</td>
          </tr>
          <tr className="font-medium">
            <td className="py-1">Total</td>
            <td className="py-1 text-right">{formatCurrency(Number(sale.total))}</td>
          </tr>
        </tbody>
      </table>

      <div>
        <p className="mb-1 text-sm font-medium">Payment</p>
        <table className="w-full text-sm">
          <tbody>
            {sale.payments.map((payment, index) => (
              <tr key={`payment-${index}`}>
                <td className="py-1 capitalize">
                  {METHOD_LABELS[payment.method] ?? payment.method}
                  {payment.reference ? <span className="text-ink-muted"> ({payment.reference})</span> : null}
                </td>
                <td className="py-1 text-right">{formatCurrency(Number(payment.amount))}</td>
              </tr>
            ))}
            {sale.payments
              .filter((payment) => payment.tendered_amount !== null)
              .map((payment, index) => (
                <React.Fragment key={`tendered-${index}`}>
                  <tr>
                    <td className="text-ink-muted py-1">Cash tendered</td>
                    <td className="py-1 text-right">{formatCurrency(Number(payment.tendered_amount))}</td>
                  </tr>
                  <tr>
                    <td className="text-ink-muted py-1">Change returned</td>
                    <td className="py-1 text-right">{formatCurrency(Number(payment.change))}</td>
                  </tr>
                </React.Fragment>
              ))}
          </tbody>
        </table>
      </div>

      <div className="text-center text-xs">
        <p>Thank you! Visit again.</p>
        <p className="text-ink-muted mt-1">{FOOTER_CREDIT}</p>
      </div>
    </div>
  );
}
