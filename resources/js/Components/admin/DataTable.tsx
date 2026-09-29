import { router } from '@inertiajs/react';
import { ChevronDown, ChevronLeft, ChevronRight, Columns3, Download, Search } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { EmptyState } from '@/Components/ui/empty-state';
import { Input } from '@/Components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { cn } from '@/lib/utils';

export interface DataTableColumn<T> {
  id: string;
  header: string;
  cell: (row: T) => React.ReactNode;
  sortable?: boolean;
  /** Hidden from the column-visibility menu — always shown, can't be toggled off (e.g. a name column). */
  alwaysVisible?: boolean;
  className?: string;
}

export interface DataTablePaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface DataTableBulkAction<T> {
  label: string;
  onAction: (selectedRows: T[]) => void;
  variant?: React.ComponentProps<typeof Button>['variant'];
}

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[];
  rows: T[];
  meta: DataTablePaginationMeta;
  getRowId: (row: T) => string | number;
  /** Current filter/sort state, as reflected in the URL — drives the controlled search input and
   * sort-indicator arrows. Comes straight from the backend response, not guessed client-side. Extra
   * keys (a page's own filters beyond search/sort/direction, e.g. a date range) pass through
   * untouched on every reload — without this, a page with additional filters would have them
   * silently dropped the moment someone used the search box or changed the sort/page. */
  filters: {
    search?: string | null;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
    [key: string]: unknown;
  };
  /** Hide the search box entirely for a page with no backend search support (e.g. filtered by
   * dedicated dropdowns instead) — showing a non-functional search field would be misleading. */
  showSearch?: boolean;
  searchPlaceholder?: string;
  bulkActions?: DataTableBulkAction<T>[];
  emptyTitle?: string;
  emptyDescription?: string;
  /** CSV of exactly what's on screen (current page, visible columns) — a full-dataset server export
   * is a separate concern (a real export endpoint) that individual pages can add on top of this. */
  csvFilename?: string;
}

/**
 * Brief §5's "backbone of the whole admin" (Phase 5 spec) — server-side pagination/sort/search via
 * Inertia partial reloads (never a full page reload), column visibility, row selection + bulk
 * actions, CSV export, and the current filter state read straight from the URL so a reload/share
 * link reproduces the exact same view. Deliberately hand-rolled rather than built on
 * @tanstack/react-table: that library's currently-installed major version (v9) has a much larger,
 * unfamiliar feature-registration API, and every actual row-model concern here (sorting, filtering,
 * pagination) is already server-driven — the library's core value doesn't apply to a table that
 * never computes its own row model.
 */
export function DataTable<T>({
  columns,
  rows,
  meta,
  getRowId,
  filters,
  showSearch = true,
  searchPlaceholder = 'Search...',
  bulkActions = [],
  emptyTitle = 'Nothing here yet',
  emptyDescription,
  csvFilename = 'export.csv',
}: DataTableProps<T>) {
  const [searchDraft, setSearchDraft] = React.useState(filters.search ?? '');
  const [selected, setSelected] = React.useState<Set<string | number>>(new Set());
  const [hiddenColumns, setHiddenColumns] = React.useState<Set<string>>(new Set());

  // Keep the search input in sync with the URL's own search param (e.g. after a browser
  // back/forward navigation) without an effect — React's documented pattern for "adjust state when
  // a prop changes" is a render-time comparison against the previous value, not useEffect+setState,
  // which would cause an extra commit/render pass for something that can happen during this one.
  const [syncedSearch, setSyncedSearch] = React.useState(filters.search ?? '');
  if ((filters.search ?? '') !== syncedSearch) {
    setSyncedSearch(filters.search ?? '');
    setSearchDraft(filters.search ?? '');
  }

  const visibleColumns = columns.filter((column) => !hiddenColumns.has(column.id));

  function reload(params: Record<string, string | number | undefined>) {
    // No `only` restriction: this component doesn't know the host page's actual Inertia prop
    // name(s), so it lets the controller's normal response drive what refreshes. Still an Inertia
    // XHR visit, not a full browser reload — `preserveState`/`preserveScroll` keep the rest of the
    // page (scroll position, any local state elsewhere) untouched.
    router.get(
      window.location.pathname,
      { ...filters, ...params } as Record<string, string | number | undefined>,
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }

  function submitSearch(event: React.FormEvent) {
    event.preventDefault();
    reload({ search: searchDraft || undefined, page: undefined });
  }

  function toggleSort(columnId: string) {
    const nextDirection = filters.sort === columnId && filters.direction === 'asc' ? 'desc' : 'asc';
    reload({ sort: columnId, direction: nextDirection, page: undefined });
  }

  function toggleRow(id: string | number) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }

  function toggleAllOnPage() {
    setSelected((prev) => {
      const pageIds = rows.map(getRowId);
      const allSelected = pageIds.every((id) => prev.has(id));
      const next = new Set(prev);
      pageIds.forEach((id) => (allSelected ? next.delete(id) : next.add(id)));
      return next;
    });
  }

  const selectedRows = rows.filter((row) => selected.has(getRowId(row)));
  const allOnPageSelected = rows.length > 0 && rows.every((row) => selected.has(getRowId(row)));

  function exportCsv() {
    const header = visibleColumns
      .map((column) => `"${column.header.replace(/"/g, '""')}"`)
      .join(',');
    const lines = rows.map((row) =>
      visibleColumns
        .map((column) => {
          const value = column.cell(row);
          const text = typeof value === 'string' || typeof value === 'number' ? String(value) : '';
          return `"${text.replace(/"/g, '""')}"`;
        })
        .join(','),
    );
    const blob = new Blob([[header, ...lines].join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', csvFilename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        {showSearch ? (
          <form onSubmit={submitSearch} className="flex max-w-sm flex-1 items-center gap-2">
            <div className="relative flex-1">
              <Search className="text-ink-muted absolute top-1/2 left-3 size-4 -translate-y-1/2" />
              <Input
                value={searchDraft}
                onChange={(event) => setSearchDraft(event.target.value)}
                placeholder={searchPlaceholder}
                className="pl-9"
              />
            </div>
          </form>
        ) : (
          <div />
        )}
        <div className="flex items-center gap-2">
          {selectedRows.length > 0 &&
            bulkActions.map((action) => (
              <Button
                key={action.label}
                type="button"
                variant={action.variant ?? 'outline'}
                size="sm"
                onClick={() => action.onAction(selectedRows)}
              >
                {action.label} ({selectedRows.length})
              </Button>
            ))}
          <Popover>
            <PopoverTrigger asChild>
              <Button type="button" variant="outline" size="sm">
                <Columns3 className="size-4" />
                Columns
              </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-56">
              <p className="text-ink-muted mb-2 text-xs font-semibold uppercase">Visible columns</p>
              <div className="space-y-2">
                {columns
                  .filter((column) => !column.alwaysVisible)
                  .map((column) => (
                    <label key={column.id} className="flex items-center gap-2 text-sm">
                      <Checkbox
                        checked={!hiddenColumns.has(column.id)}
                        onCheckedChange={(checked) =>
                          setHiddenColumns((prev) => {
                            const next = new Set(prev);
                            if (checked) {
                              next.delete(column.id);
                            } else {
                              next.add(column.id);
                            }
                            return next;
                          })
                        }
                      />
                      {column.header}
                    </label>
                  ))}
              </div>
            </PopoverContent>
          </Popover>
          <Button type="button" variant="outline" size="sm" onClick={exportCsv}>
            <Download className="size-4" />
            Export CSV
          </Button>
        </div>
      </div>

      {rows.length === 0 ? (
        <EmptyState title={emptyTitle} description={emptyDescription} />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              {bulkActions.length > 0 && (
                <TableHead className="w-10">
                  <Checkbox checked={allOnPageSelected} onCheckedChange={toggleAllOnPage} />
                </TableHead>
              )}
              {visibleColumns.map((column) => (
                <TableHead key={column.id} className={column.className}>
                  {column.sortable ? (
                    <button
                      type="button"
                      onClick={() => toggleSort(column.id)}
                      className="hover:text-ink flex items-center gap-1"
                    >
                      {column.header}
                      {filters.sort === column.id && (
                        <ChevronDown
                          className={cn(
                            'size-3.5 transition-transform',
                            filters.direction === 'asc' && 'rotate-180',
                          )}
                        />
                      )}
                    </button>
                  ) : (
                    column.header
                  )}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((row) => {
              const id = getRowId(row);

              return (
                <TableRow key={id}>
                  {bulkActions.length > 0 && (
                    <TableCell>
                      <Checkbox checked={selected.has(id)} onCheckedChange={() => toggleRow(id)} />
                    </TableCell>
                  )}
                  {visibleColumns.map((column) => (
                    <TableCell key={column.id} className={column.className}>
                      {column.cell(row)}
                    </TableCell>
                  ))}
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      )}

      {meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <p className="text-ink-muted">
            {meta.from}–{meta.to} of {meta.total}
          </p>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={meta.current_page <= 1}
              onClick={() => reload({ page: meta.current_page - 1 })}
            >
              <ChevronLeft className="size-4" />
              Previous
            </Button>
            <span className="text-ink-muted">
              Page {meta.current_page} of {meta.last_page}
            </span>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => reload({ page: meta.current_page + 1 })}
            >
              Next
              <ChevronRight className="size-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
