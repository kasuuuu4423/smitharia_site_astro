import 'swiper/css';
import 'swiper/css/pagination';
import { A11y, Pagination } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/react';

import { decodeHtmlEscape } from '../../lib/Util';
import { type Work } from '../../lib/WP.ts';
import ToggleFilteredImage, { parentClass } from '../common/ToggleFilteredImage';

const Slider = (props: { works: Work[]; limited?: boolean }) => {
  return (
    <section aria-label="おすすめ作品" className="recommendedWorks w-full">
      <Swiper
        className="w-full"
        slidesPerView={1}
        loop={props.works.length > 1}
        modules={[A11y, Pagination]}
        a11y={{
          containerRoleDescriptionMessage: 'おすすめ作品のスライダー',
          itemRoleDescriptionMessage: '作品',
          slideLabelMessage: '{{index}} / {{slidesLength}}',
          scrollOnFocus: false,
        }}
        pagination={{
          enabled: props.works.length > 1,
          clickable: true,
          bulletElement: 'button',
          renderBullet: (index, className) =>
            `<button type="button" class="${className}" aria-label="おすすめ作品 ${index + 1}件目を表示"></button>`,
        }}
      >
        {props.works.map((work) => (
          <SwiperSlide key={work.id}>
            {({ isActive }) => (
              <a
                className={`${parentClass} block text-white`}
                href={`${props.limited ? '/limited' : ''}/work/${work.id}`}
                tabIndex={isActive ? 0 : -1}
                aria-hidden={!isActive}
              >
                <div className="slideImage">
                  <ToggleFilteredImage imgPath={work.acf.thumbnail.url} alt="" />
                </div>
                <h2 className="px-6 md:px-0 py-4 text-base md:text-xl leading-relaxed font-serif">
                  {decodeHtmlEscape(work.title.rendered)}
                </h2>
              </a>
            )}
          </SwiperSlide>
        ))}
      </Swiper>
      <style>{`
        .recommendedWorks .slideImage { height: 50vh; min-height: 240px; }
        .recommendedWorks .swiper-pagination { position: static; display: flex; justify-content: center; flex-wrap: wrap; padding-bottom: 8px; }
        .recommendedWorks .swiper-pagination-bullet { display: grid; place-items: center; width: 44px; height: 44px; margin: 0 !important; border-radius: 0; background: transparent; opacity: 1; }
        .recommendedWorks .swiper-pagination-bullet::before { content: ''; width: 12px; height: 12px; border: 1px solid white; border-radius: 50%; background: transparent; }
        .recommendedWorks .swiper-pagination-bullet-active::before { background: white; }
        .recommendedWorks .swiper a:focus-visible { outline-offset: -4px; }
        @media (min-width: 768px) { .recommendedWorks .slideImage { height: 70vh; } }
      `}</style>
    </section>
  );
};
export default Slider;
