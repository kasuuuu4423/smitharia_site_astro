import React from 'react';

interface Props {
    text: string;
}

const Scrollate: React.FC<Props> = ({ text }) => {
    return (
        <div className="scrollate flex my-8 justify-center flex-wrap">
            <div className="line h-[50px] md:h-[100px] border-r border-white w-px"></div>
            <div className="text-center text-white font-serif w-full my-3">{text}</div>
            <div className="line h-[50px] md:h-[100px] border-r border-white w-px"></div>
        </div>
    );
};

export default Scrollate; 