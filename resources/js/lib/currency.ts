/**
 * Rs. 2,500 — no decimals (Decision, Phase 14 currency migration). Hand-formatted rather than
 * `Intl.NumberFormat(..., { currency: 'PKR' })`, whose symbol/spacing output varies by ICU data
 * across browsers/environments (sometimes "PKR", sometimes "₨") — this guarantees the exact shape
 * everywhere the app displays money.
 */
export function formatCurrency(amount: number | string): string {
  const value = Math.round(Number(amount));

  return `Rs. ${value.toLocaleString('en-US')}`;
}
