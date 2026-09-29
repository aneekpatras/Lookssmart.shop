import { Construction } from 'lucide-react';

import { Badge } from '@/Components/ui/badge';

export function PlaceholderSection({
  title,
  description,
  phase,
}: {
  title: string;
  description: string;
  phase: string;
}) {
  return (
    <div className="mx-auto flex max-w-3xl flex-col items-center gap-4 px-6 py-24 text-center">
      <div className="bg-accent-50 text-accent-600 flex size-14 items-center justify-center rounded-full">
        <Construction className="size-7" />
      </div>
      <h1 className="font-display text-ink text-3xl font-medium">{title}</h1>
      <p className="text-ink-muted">{description}</p>
      <Badge variant="accent">{phase}</Badge>
    </div>
  );
}
