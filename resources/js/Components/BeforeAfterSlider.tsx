import { useState, useRef } from 'react';

import { Picture } from '@/Components/Picture';

interface BeforeAfterSliderProps {
  beforeImage: string;
  beforeImageWebp?: string | null;
  afterImage: string;
  afterImageWebp?: string | null;
  beforeLabel?: string;
  afterLabel?: string;
  caption?: string;
}

export function BeforeAfterSlider({
  beforeImage,
  beforeImageWebp,
  afterImage,
  afterImageWebp,
  beforeLabel = 'Before',
  afterLabel = 'After',
  caption,
}: BeforeAfterSliderProps) {
  const [sliderPosition, setSliderPosition] = useState(50);
  const containerRef = useRef<HTMLDivElement>(null);

  const handleMouseDown = () => {
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
  };

  const handleTouchStart = () => {
    document.addEventListener('touchmove', handleTouchMove);
    document.addEventListener('touchend', handleTouchEnd);
  };

  const handleMouseMove = (e: MouseEvent) => {
    if (containerRef.current) {
      const rect = containerRef.current.getBoundingClientRect();
      const newPosition = ((e.clientX - rect.left) / rect.width) * 100;
      setSliderPosition(Math.min(Math.max(newPosition, 0), 100));
    }
  };

  const handleTouchMove = (e: TouchEvent) => {
    const touch = e.touches[0];
    if (containerRef.current && touch) {
      const rect = containerRef.current.getBoundingClientRect();
      const newPosition = ((touch.clientX - rect.left) / rect.width) * 100;
      setSliderPosition(Math.min(Math.max(newPosition, 0), 100));
    }
  };

  const handleMouseUp = () => {
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', handleMouseUp);
  };

  const handleTouchEnd = () => {
    document.removeEventListener('touchmove', handleTouchMove);
    document.removeEventListener('touchend', handleTouchEnd);
  };

  return (
    <div className="flex flex-col items-center">
      {/* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */}
      <div
        ref={containerRef}
        className="relative w-full aspect-square md:aspect-video rounded-lg overflow-hidden bg-gray-200 cursor-ew-resize group select-none"
        role="application"
        aria-label={`Before and after comparison: ${beforeLabel} and ${afterLabel}`}
        onMouseDown={handleMouseDown}
        onTouchStart={handleTouchStart}
      >
        {/* After image (background) */}
        <Picture
          src={afterImage}
          webpSrc={afterImageWebp}
          alt={afterLabel}
          className="absolute inset-0 w-full h-full object-cover"
          draggable={false}
        />

        {/* Before image (overlay) */}
        <div className="absolute inset-0 overflow-hidden" style={{ width: `${sliderPosition}%` }}>
          <Picture
            src={beforeImage}
            webpSrc={beforeImageWebp}
            alt={beforeLabel}
            className="w-full h-full object-cover"
            draggable={false}
          />
        </div>

        {/* Divider line with handle */}
        <div
          className="absolute top-0 bottom-0 w-1 bg-white cursor-ew-resize group-hover:w-1.5 transition-all"
          style={{ left: `${sliderPosition}%`, transform: 'translateX(-50%)' }}
          role="slider"
          aria-label="Image comparison slider"
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={Math.round(sliderPosition)}
          tabIndex={0}
          onKeyDown={(e) => {
            if (e.key === 'ArrowLeft') {
              setSliderPosition(Math.max(sliderPosition - 5, 0));
            } else if (e.key === 'ArrowRight') {
              setSliderPosition(Math.min(sliderPosition + 5, 100));
            }
          }}
        >
          <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-full p-2 shadow-lg group-hover:scale-110 transition-transform">
            <div className="flex gap-1">
              <div className="w-1 h-4 bg-gray-600" />
              <div className="w-1 h-4 bg-gray-600" />
            </div>
          </div>
        </div>

        {/* Labels */}
        <div className="absolute top-4 left-4 bg-black/50 text-white px-3 py-1.5 rounded text-sm font-medium">
          {beforeLabel}
        </div>
        <div className="absolute top-4 right-4 bg-black/50 text-white px-3 py-1.5 rounded text-sm font-medium">
          {afterLabel}
        </div>
      </div>

      {caption && <p className="mt-3 text-center text-sm text-gray-600">{caption}</p>}
    </div>
  );
}
