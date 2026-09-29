import { ErrorPage } from '@/Pages/Errors/Layout';

export default function Error500() {
  return (
    <ErrorPage
      code="500"
      title="Something went wrong"
      description="An unexpected error occurred on our end. Please try again in a moment."
    />
  );
}
