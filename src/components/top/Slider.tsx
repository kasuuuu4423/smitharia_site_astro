import { Swiper, SwiperSlide } from 'swiper/react';
import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";
import Navigation from 'swiper';
import AutoPlay from 'swiper';
import Pagination from 'swiper';

import { type Work } from '../../lib/WP';

const Slider = (props: {works: Work[]}) =>{

    return(
        <section className='w-full'>
            <Swiper
                className='w-full md:h-full h-96'
                slidesPerView={1}
                loop={true}
            >
                {props.works.map(work=>
                    <SwiperSlide className='w-full' key={work.id}>
                        <img className="w-full h-full object-cover object-bottom" loading='lazy' src={work.acf.thumbnail.url} alt={work.title.rendered} />
                    </SwiperSlide>
                )}
            </Swiper>
            <style>{`
            .swiper-slide{
                height: 80vh;
            }
            `}</style>
        </section>
    );
}

export default Slider;