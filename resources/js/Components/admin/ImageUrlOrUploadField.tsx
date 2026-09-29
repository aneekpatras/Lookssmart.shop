import { FormField } from '@/Components/admin/ResourceForm';
import { Input } from '@/Components/ui/input';

interface ImageUrlOrUploadFieldProps {
  idPrefix: string;
  label: string;
  /** The resolved image to preview — an uploaded file's local object URL, or the current/pasted
   * remote URL, whichever the backend would actually display (see `displayImageUrl()` on
   * ServiceController/DealController). */
  previewUrl: string | null;
  fileError?: string;
  urlValue: string;
  urlError?: string;
  onFileChange: (file: File | null) => void;
  onUrlChange: (value: string) => void;
  accept?: string;
}

/**
 * Shared by ServiceForm/DealForm and their list-page quick-edit modals: an uploaded file always
 * takes priority over a pasted URL when both are given in the same save (see
 * ServiceController::applyImage()/DealController::applyImage()) — the URL is only ever the
 * fallback, so it's presented that way here rather than as two equal, competing inputs.
 */
export function ImageUrlOrUploadField({
  idPrefix,
  label,
  previewUrl,
  fileError,
  urlValue,
  urlError,
  onFileChange,
  onUrlChange,
  accept = 'image/jpeg,image/png,image/webp,image/gif',
}: ImageUrlOrUploadFieldProps) {
  return (
    <FormField label={label} htmlFor={`${idPrefix}-image`} error={fileError}>
      <div className="space-y-3">
        {previewUrl ? (
          <img
            src={previewUrl}
            alt=""
            className="border-border-soft h-32 w-full rounded-md border object-cover sm:w-48"
          />
        ) : null}
        <Input
          id={`${idPrefix}-image`}
          type="file"
          accept={accept}
          onChange={(e) => onFileChange(e.target.files?.[0] ?? null)}
        />
        <div className="flex items-center gap-2">
          <div className="border-border-soft h-px flex-1 border-t" />
          <span className="text-ink-muted text-xs">OR PASTE A URL</span>
          <div className="border-border-soft h-px flex-1 border-t" />
        </div>
        <div className="space-y-1">
          <Input
            id={`${idPrefix}-stock-image-url`}
            type="url"
            inputMode="url"
            placeholder="https://images.example.com/photo.jpg"
            value={urlValue}
            onChange={(e) => onUrlChange(e.target.value)}
            aria-invalid={!!urlError}
          />
          {urlError ? (
            <p className="text-sm text-red-600" role="alert">
              {urlError}
            </p>
          ) : (
            <p className="text-ink-muted text-xs">
              Used only when no file is uploaded here — an uploaded file always wins.
            </p>
          )}
        </div>
      </div>
    </FormField>
  );
}
