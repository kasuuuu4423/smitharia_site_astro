import './Slider.css';
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
    </section>
  );
};
export default Slider;
