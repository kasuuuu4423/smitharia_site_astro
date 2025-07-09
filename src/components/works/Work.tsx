import React from 'react';
import { type Work } from '../../lib/WP';
import ToggleFilteredImage, { parentClass } from '../common/ToggleFilteredImage';
import { decodeHtmlEscape } from '../../lib/Util';

interface Props {
    work: Work;
    limited?: boolean;
}

const WorkComponent: React.FC<Props> = ({ work, limited }) => {
    const workClass = parentClass
        + `
			[&_h2]:hover:opacity-100
			[&_h2]:hover:top-1/2
			mb-28
			md:mb-0 h-[300px]
			md:h-full
			`
        + (work.acf.extend_column ? "col-span-2 " : "")
        + (work.acf.extend_row ? "row-span-2" : "");

    return (
        <div className={workClass}>
            <a className="" href={"/" + (limited ? "limited/" : "") + "work/" + work.id}>
                <ToggleFilteredImage
                    imgPath={work.acf.thumbnail.url}
                    alt={work.acf.thumbnail.alt}
                />
                <h2 className="
					md:-translate-x-1/2
					md:m-0
					md:absolute
					md:top-[calc(50%+1rem)]
					md:left-1/2
					md:opacity-0
					md:duration-200
					md:text-center
					w-1/2
					text-white
					-translate-y-1/2
					drop-shadow
					font-serif
					ml-10"
                >
                    {decodeHtmlEscape(work.title.rendered)}
                </h2>
            </a>
        </div>
    );
};

export default WorkComponent; 