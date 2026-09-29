import { useCallback, useEffect, useState, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, ChevronLeft, ChevronRight } from 'lucide-react';

interface Image {
  id: number;
  image_path: string;
  caption?: string | null;
}

interface LightboxProps {
  images: Image[];
  initialIndex: number;
  isOpen: boolean;
  onClose: () => void;
}

const FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function Lightbox({ images, initialIndex, isOpen, onClose }: LightboxProps) {
  const [currentIndex, setCurrentIndex] = useState(initialIndex);
  const previousInitialIndexRef = useRef(initialIndex);
  const dialogRef = useRef<HTMLDivElement>(null);
  const previouslyFocusedRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (previousInitialIndexRef.current !== initialIndex) {
      setCurrentIndex(initialIndex);
      previousInitialIndexRef.current = initialIndex;
    }
  }, [initialIndex]);

  const goToPrevious = useCallback(() => {
    setCurrentIndex((prev) => (prev === 0 ? images.length - 1 : prev - 1));
  }, [images.length]);

  const goToNext = useCallback(() => {
    setCurrentIndex((prev) => (prev === images.length - 1 ? 0 : prev + 1));
  }, [images.length]);

  // Focus management: move focus into the dialog on open (there is otherwise nothing keyboard-
  // reachable inside it until the user tabs from wherever focus happened to already be), and give it
  // back to whatever triggered the lightbox on close — a real focus trap, not just Escape-to-close.
  useEffect(() => {
    if (isOpen) {
      previouslyFocusedRef.current = document.activeElement as HTMLElement | null;
      dialogRef.current?.focus();
    } else {
      previouslyFocusedRef.current?.focus();
    }
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
        return;
      }

      if (e.key === 'ArrowLeft') {
        goToPrevious();
        return;
      }

      if (e.key === 'ArrowRight') {
        goToNext();
        return;
      }

      if (e.key === 'Tab' && dialogRef.current) {
        const focusable = Array.from(dialogRef.current.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR));
        if (focusable.length === 0) return;

        const first = focusable[0]!;
        const last = focusable[focusable.length - 1]!;

        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose, goToPrevious, goToNext]);

  if (images.length === 0) return null;

  const currentImage = images[currentIndex]!;

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.2 }}
          onClick={onClose}
          className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4 outline-none"
          role="dialog"
          aria-label="Image lightbox"
          aria-modal="true"
          ref={dialogRef}
          tabIndex={-1}
        >
          <motion.div
            initial={{ scale: 0.9, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            exit={{ scale: 0.9, opacity: 0 }}
            transition={{ duration: 0.2 }}
            onClick={(e) => e.stopPropagation()}
            className="relative w-full h-full max-w-4xl max-h-[90vh] flex flex-col"
          >
            {/* Close button */}
            <button
              onClick={onClose}
              className="absolute -top-10 right-0 text-white hover:text-gray-300 transition-colors z-10 p-2"
              aria-label="Close lightbox"
            >
              <X size={24} />
            </button>

            {/* Image container */}
            <div className="flex-1 flex items-center justify-center overflow-hidden rounded-lg bg-black">
              <motion.img
                key={currentImage.id}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                transition={{ duration: 0.3 }}
                src={currentImage.image_path}
                alt={currentImage.caption || `Image ${currentIndex + 1}`}
                className="w-full h-full object-contain"
              />
            </div>

            {/* Caption */}
            {currentImage.caption && (
              <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, delay: 0.1 }}
                className="mt-4 text-center text-white text-sm md:text-base"
              >
                {currentImage.caption}
              </motion.div>
            )}

            {/* Image counter */}
            <div className="mt-4 text-center text-white/70 text-sm">
              {currentIndex + 1} / {images.length}
            </div>

            {/* Navigation buttons */}
            {images.length > 1 && (
              <>
                <button
                  onClick={goToPrevious}
                  className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-12 md:-translate-x-16 text-white hover:text-gray-300 transition-colors p-2"
                  aria-label="Previous image"
                >
                  <ChevronLeft size={32} />
                </button>

                <button
                  onClick={goToNext}
                  className="absolute right-0 top-1/2 -translate-y-1/2 translate-x-12 md:translate-x-16 text-white hover:text-gray-300 transition-colors p-2"
                  aria-label="Next image"
                >
                  <ChevronRight size={32} />
                </button>
              </>
            )}
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
