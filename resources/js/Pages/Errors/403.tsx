import { ErrorPage } from '@/Pages/Errors/Layout';

export default function Error403() {
  return (
    <ErrorPage
      code="403"
      title="Access denied"
      description="You don't have permission to view this page. If you think this is a mistake, please contact us."
    />
  );
}
