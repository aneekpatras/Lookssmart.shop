import { ErrorPage } from '@/Pages/Errors/Layout';

export default function Error404() {
  return (
    <ErrorPage
      code="404"
      title="Page not found"
      description="The page you're looking for doesn't exist or may have moved."
    />
  );
}
