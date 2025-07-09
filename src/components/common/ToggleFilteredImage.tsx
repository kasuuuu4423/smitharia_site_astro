import React, { useEffect, useRef } from 'react';
import { convertImagePathToFiltered } from '../../lib/WP';
import LoadImage from '../../assets/images/load.gif';

interface Props {
    imgPath: string;
    alt: string;
    reverse?: boolean;
}

export const parentClass = `
	[&_img.normal]:hover:opacity-0
	relative `;

const ToggleFilteredImage: React.FC<Props> = ({ imgPath, alt, reverse }) => {
    const filteredImgPath = convertImagePathToFiltered(imgPath);
    const normalRef = useRef<HTMLImageElement>(null);
    const filteredRef = useRef<HTMLImageElement>(null);
    const loadRef = useRef<HTMLImageElement>(null);

    useEffect(() => {
        const normal = normalRef.current;
        const filtered = filteredRef.current;
        const load = loadRef.current;

        if (!normal || !filtered || !load) return;

        const checkImageLoaded = () => {
            if (normal.complete) {
                load.style.opacity = '0.03';
                filtered.classList.remove('opacity-0');
                normal.classList.remove('opacity-0');
            } else {
                // 画像がまだロードされていない場合、少し待ってから再チェック
                setTimeout(checkImageLoaded, 100);
            }
        };

        checkImageLoaded();
    }, []);

    const imageStyle: React.CSSProperties = {
        transition: '1s cubic-bezier(0, 0.3, 0, 0.9)'
    };

    return (
        <div className="toggle_image relative w-full h-full">
            <img
                ref={filteredRef}
                className="opacity-0 filtered w-full h-full object-cover"
                src={reverse ? imgPath : filteredImgPath}
                alt={alt}
                style={imageStyle}
            />
            <img
                ref={normalRef}
                className="opacity-0 normal absolute duration-200 top-0 left-0 w-full h-full object-cover"
                src={reverse ? filteredImgPath : imgPath}
                alt={alt}
                style={imageStyle}
            />
            <img
                ref={loadRef}
                className="load absolute duration-200 top-0 left-0 w-full h-full object-cover"
                src={LoadImage.src}
                alt=""
                style={imageStyle}
            />
        </div>
    );
};

export default ToggleFilteredImage; 