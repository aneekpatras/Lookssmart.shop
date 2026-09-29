import { Head } from '@inertiajs/react';

import { PlaceholderSection } from '@/Components/PlaceholderSection';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Reminders() {
  return (
    <>
      <Head title="Auto Reminders" />
      <PlaceholderSection
        title="Auto Reminders"
        description="The rule builder for offset-based email/SMS/WhatsApp reminders is built in Phase 8 (Notifications & Reminders)."
        phase="Phase 8"
      />
    </>
  );
}

Reminders.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
