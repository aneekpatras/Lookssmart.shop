import { Head, Link, router } from '@inertiajs/react';
import * as React from 'react';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/currency';

interface CustomerRow {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  ltv: string;
  visits: number;
  no_show_count: number;
  loyalty_points: number;
  tags: string[];
  is_blacklisted: boolean;
}

interface CustomersIndexPageProps {
  customers: CustomerRow[];
  filters: { search: string | null; tag: string | null };
}


export default function CustomersIndex({ customers, filters }: CustomersIndexPageProps) {
  const [search, setSearch] = React.useState(filters.search ?? '');

  function submit(event: React.FormEvent) {
    event.preventDefault();
    router.get(
      '/admin/customers',
      { search: search || undefined, tag: filters.tag || undefined },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }

  return (
    <>
      <Head title="Customers" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Customers</h1>
          <p className="text-ink-muted text-sm">Lifetime value, visit history, and loyalty at a glance.</p>
        </div>

        <form onSubmit={submit} className="flex items-end gap-2">
          <div className="space-y-1.5">
            <Label htmlFor="customer-search">Search</Label>
            <Input
              id="customer-search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Name, email, or phone..."
              className="w-64"
            />
          </div>
          <Button type="submit" variant="outline">
            Search
          </Button>
        </form>

        <div className="border-border-soft overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-accent-50/60">
              <tr>
                <th className="p-3 text-left">Customer</th>
                <th className="p-3 text-left">LTV</th>
                <th className="p-3 text-left">Visits</th>
                <th className="p-3 text-left">No-shows</th>
                <th className="p-3 text-left">Loyalty pts</th>
                <th className="p-3 text-left">Tags</th>
                <th className="p-3 text-left" />
              </tr>
            </thead>
            <tbody className="divide-border-soft divide-y">
              {customers.map((customer) => (
                <tr key={customer.id} className="hover:bg-accent-50/40">
                  <td className="p-3">
                    <p className="font-medium">{customer.name}</p>
                    <p className="text-ink-muted text-xs">{customer.email}</p>
                  </td>
                  <td className="p-3">{formatCurrency(customer.ltv)}</td>
                  <td className="p-3">{customer.visits}</td>
                  <td className="p-3">{customer.no_show_count}</td>
                  <td className="p-3">{customer.loyalty_points}</td>
                  <td className="p-3">
                    <div className="flex flex-wrap gap-1">
                      {customer.is_blacklisted && <Badge variant="destructive">Blacklisted</Badge>}
                      {customer.tags.map((tag) => (
                        <Badge key={tag} variant="outline">
                          {tag}
                        </Badge>
                      ))}
                    </div>
                  </td>
                  <td className="p-3 text-right">
                    <Button asChild size="sm" variant="outline">
                      <Link href={`/admin/customers/${customer.id}`}>View profile</Link>
                    </Button>
                  </td>
                </tr>
              ))}
              {customers.length === 0 && (
                <tr>
                  <td colSpan={7} className="text-ink-muted p-6 text-center text-sm">
                    No customers found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}

CustomersIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
