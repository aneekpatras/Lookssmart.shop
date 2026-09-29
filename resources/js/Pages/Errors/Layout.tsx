import { Link } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';

export function ErrorPage({
  code,
  title,
  description,
}: {
  code: string;
  title: string;
  description: string;
}) {
  return (
    <div className="bg-ivory flex min-h-screen flex-col items-center justify-center gap-4 px-6 text-center">
      <p className="font-display text-accent-500 text-6xl font-medium">{code}</p>
      <h1 className="font-display text-ink text-2xl font-medium">{title}</h1>
      <p className="text-ink-muted max-w-md text-sm">{description}</p>
      <Button asChild>
        <Link href="/">Back to home</Link>
      </Button>
    </div>
  );
}
