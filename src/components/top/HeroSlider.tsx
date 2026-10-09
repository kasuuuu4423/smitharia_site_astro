import { useEffect, useState } from 'react';
import { A11y, EffectFade } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/react';

import 'swiper/css';
import 'swiper/css/effect-fade';
import './HeroSlider.css';
import NoiseImage from '../../assets/images/load.gif';
import { decodeHtmlEscape } from '../../lib/Util';

import type { Work } from '../../lib/WP';
import type { Swiper as SwiperInstance } from 'swiper';

const HeroSlider = ({ works, limited = false }: { works: Work[]; limited?: boolean }) => {
  const [swiper, setSwiper] = useState<SwiperInstance | null>(null);
  const [activeIndex, setActiveIndex] = useState(0);
  const [reducedMotion, setReducedMotion] = useState(true);
  const activeWork = works[activeIndex];
  const hasMultipleSlides = works.length > 1;

  useEffect(() => {
    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    setReducedMotion(motionQuery.matches);
    const handleMotionChange = () => {
      setReducedMotion(motionQuery.matches);
    };
    motionQuery.addEventListener('change', handleMotionChange);
    return () => motionQuery.removeEventListener('change', handleMotionChange);
  }, []);

  const selectSlide = (index: number) => {
    swiper?.slideToLoop(index);
  };

  return (
    <div
      className="heroSlider"
      role="region"
      aria-roledescription="カルーセル"
      aria-label="ライブ・展示・映像の作品"
    >
      <Swiper
        className="heroSlideshow"
        modules={[A11y, EffectFade]}
        effect="fade"
        fadeEffect={{ crossFade: true }}
        loop={hasMultipleSlides}
        speed={reducedMotion ? 0 : 700}
        a11y={{
          containerRoleDescriptionMessage: '作品のスライダー',
          itemRoleDescriptionMessage: '作品',
          slideLabelMessage: '{{index}} / {{slidesLength}}',
          scrollOnFocus: false,
        }}
        onSwiper={setSwiper}
        onSlideChange={(instance) => setActiveIndex(instance.realIndex)}
      >
        {works.map((work, index) => (
          <SwiperSlide key={work.id}>
            <img
              className="heroSlideImage"
              src={work.acf.thumbnail.url}
              alt={decodeHtmlEscape(work.title.rendered)}
              width="1200"
              height="800"
              fetchPriority={index === 0 ? 'high' : 'auto'}
              loading={index === 0 ? 'eager' : 'lazy'}
              decoding="async"
            />
          </SwiperSlide>
        ))}
      </Swiper>
      <img className="heroSliderNoise" src={NoiseImage.src} alt="" aria-hidden="true" />
      {activeWork && (
        <a
          className="heroSlideCredit"
          href={`${limited ? '/limited' : ''}/work/${activeWork.id}/`}
          title={decodeHtmlEscape(activeWork.title.rendered)}
        >
          <span>{decodeHtmlEscape(activeWork.title.rendered)}</span>
          <span aria-hidden="true">↗</span>
        </a>
      )}
      {hasMultipleSlides && (
        <div className="heroSliderControls" aria-label="写真の切り替え">
          <button
            type="button"
            aria-label="前の作品を表示"
            onClick={() => {
              swiper?.slidePrev();
            }}
          >
            <span aria-hidden="true">←</span>
          </button>
          <div className="heroSliderDots">
            {works.map((work, index) => (
              <button
                key={work.id}
                type="button"
                aria-label={`${decodeHtmlEscape(work.title.rendered)}を表示`}
                aria-current={index === activeIndex ? 'true' : undefined}
                onClick={() => selectSlide(index)}
              >
                <span aria-hidden="true" />
              </button>
            ))}
          </div>
          <button
            type="button"
            aria-label="次の作品を表示"
            onClick={() => {
              swiper?.slideNext();
            }}
          >
            <span aria-hidden="true">→</span>
          </button>
        </div>
      )}
    </div>
  );
};

export default HeroSlider;
