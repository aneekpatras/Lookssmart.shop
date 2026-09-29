import { ErrorPage } from '@/Pages/Errors/Layout';

export default function Error419() {
  return (
    <ErrorPage
      code="419"
      title="Session expired"
      description="Your session expired for your security. Please refresh the page and try again."
    />
  );
}
