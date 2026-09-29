import type { ReceiptSale } from './ReceiptBody';

const FOOTER_CREDIT = 'Software developed by Aneek | 03199154505';

/** Characters per line a real 80mm/Courier New/11px thermal roll fits — matches the reference
 * template's column layout (Item | Qty | Price | Amt). */
const WIDTH = 40;

function pesos(value: number): string {
  return value.toFixed(2);
}

function padRight(text: string, width: number): string {
  return text.length >= width ? text.slice(0, width) : text + ' '.repeat(width - text.length);
}

function padLeft(text: string, width: number): string {
  return text.length >= width ? text.slice(text.length - width) : ' '.repeat(width - text.length) + text;
}

function center(text: string): string {
  if (text.length >= WIDTH) {
    return text.slice(0, WIDTH);
  }
  const left = Math.floor((WIDTH - text.length) / 2);
  return ' '.repeat(left) + text;
}

function dashes(): string {
  return '-'.repeat(WIDTH);
}

/** Item | Qty | Price | Amt, 18/4/9/9 columns (sums to 40). Long names wrap onto their own line
 * above the qty/price/amt row, per the reference template's "properly wrapped text" requirement. */
function itemRow(description: string, qty: number, price: number, amt: number): string {
  const numbers = padLeft(String(qty), 4) + padLeft(pesos(price), 9) + padLeft(pesos(amt), 9);

  if (description.length <= 18) {
    return padRight(description, 18) + numbers;
  }

  return description + '\n' + padRight('', 18) + numbers;
}

function totalRow(label: string, amount: string): string {
  return padRight(label, WIDTH - 12) + padLeft(amount, 12);
}

export function ThermalReceipt({ sale }: { sale: ReceiptSale }) {
  const lines: string[] = [];

  if (sale.business?.name) {
    lines.push(center(sale.business.name.toUpperCase()));
  }
  if (sale.business?.address) {
    lines.push(center(sale.business.address));
  }
  if (sale.business?.phone) {
    lines.push(center(`PHONE: ${sale.business.phone}`));
  }
  lines.push(dashes());
  lines.push(`Invoice #: ${sale.sale_number}`);
  lines.push(`Date: ${sale.created_at ? new Date(sale.created_at).toLocaleString() : ''}`);
  lines.push(`Customer: ${sale.customer_name ?? 'Walk-in Customer'}`);
  lines.push(`Cashier: ${sale.created_by ?? 'Staff'}`);
  lines.push(dashes());
  lines.push(padRight('Item', 18) + padLeft('Qty', 4) + padLeft('Price', 9) + padLeft('Amt', 9));
  lines.push(dashes());

  sale.items.forEach((item) => {
    lines.push(itemRow(item.description, item.quantity, Number(item.unit_price), Number(item.total)));
  });

  lines.push(dashes());
  lines.push(totalRow('SubTotal', pesos(Number(sale.subtotal))));

  if (sale.discount_percent && Number(sale.discount_percent) > 0) {
    lines.push(totalRow(`Discount (${sale.discount_percent}%)`, `-${pesos(Number(sale.discount))}`));
  } else if (Number(sale.discount) > 0) {
    lines.push(totalRow('Discount', `-${pesos(Number(sale.discount))}`));
  }

  if (sale.tax_rate_percent && Number(sale.tax_rate_percent) > 0) {
    lines.push(totalRow(`Tax (${sale.tax_rate_percent}%)`, pesos(Number(sale.tax))));
  } else if (Number(sale.tax) > 0) {
    lines.push(totalRow('Tax', pesos(Number(sale.tax))));
  }

  lines.push(dashes());
  lines.push(totalRow('TOTAL', `Rs. ${pesos(Number(sale.total))}`));
  lines.push(dashes());

  sale.payments.forEach((payment) => {
    const label = payment.method === 'loyalty_points' ? 'Loyalty Points' : payment.method === 'card' ? 'Card' : 'Cash';
    lines.push(totalRow(label, pesos(Number(payment.amount))));

    if (payment.tendered_amount !== null) {
      lines.push(totalRow('Cash Tendered', pesos(Number(payment.tendered_amount))));
      lines.push(totalRow('Change Returned', pesos(Number(payment.change))));
    }
  });

  lines.push(dashes());
  lines.push(center('Thank you! Visit again.'));
  lines.push(center(FOOTER_CREDIT));

  return (
    <pre id="thermal-receipt-print" className="hidden font-mono text-[11px] leading-tight whitespace-pre print:block">
      {lines.join('\n')}
    </pre>
  );
}
