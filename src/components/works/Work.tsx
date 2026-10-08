import React from 'react';

import { decodeHtmlEscape } from '../../lib/Util';
import { type Work } from '../../lib/WP';
import ToggleFilteredImage, { parentClass } from '../common/ToggleFilteredImage';

interface Props {
  work: Work;
  limited?: boolean;
}
const WorkComponent: React.FC<Props> = ({ work, limited }) => {
  const title = decodeHtmlEscape(work.title.rendered);
  const workClass = `${parentClass} min-w-0 mb-12 md:mb-0 ${work.acf.extend_column ? 'col-span-2' : ''} ${work.acf.extend_row ? 'row-span-2' : ''}`;
  return (
    <div className={workClass}>
      <a
        className="flex h-full flex-col text-white"
        href={`${limited ? '/limited' : ''}/work/${work.id}`}
      >
        <div className="relative [&>.toggle_image]:absolute [&>.toggle_image]:inset-0 h-[300px] md:min-h-[200px] md:h-auto md:flex-1 overflow-hidden">
          <ToggleFilteredImage imgPath={work.acf.thumbnail.url} alt="" />
        </div>
        <h2 className="px-6 md:px-0 py-3 text-base leading-relaxed font-serif">{title}</h2>
      </a>
    </div>
  );
};
export default WorkComponent;
