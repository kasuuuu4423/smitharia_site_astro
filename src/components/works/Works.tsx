import React, { useState, useEffect, useCallback } from 'react';
import { getWorks, getRecommendedWorks, type Work } from '../../lib/WP';
import Slider from '../top/Slider';
import Scrollate from '../common/Scrollate';
import WorkComponent from './Work';

interface Props {
    filterCatId?: number;
    disableSlider?: boolean;
    limited: boolean;
}

const Works: React.FC<Props> = ({ filterCatId, disableSlider, limited }) => {
    const [works, setWorks] = useState<Work[]>([]);
    const [recommendWorks, setRecommendWorks] = useState<Work[]>([]);
    const [page, setPage] = useState(2);
    const [loading, setLoading] = useState(true);
    const [hasMore, setHasMore] = useState(true);

    // 初期データの取得
    useEffect(() => {
        const fetchInitialWorks = async () => {
            try {
                setLoading(true);
                let initialWorks = await getWorks({ 'per_page': "20" });

                // 配列でない場合の安全性チェック
                if (!Array.isArray(initialWorks)) {
                    console.error('初期作品データが配列ではありません:', initialWorks);
                    initialWorks = [];
                }

                if (!limited) {
                    initialWorks = initialWorks.filter(work => !work.acf.limited);
                }

                const recommendWorks = await getRecommendedWorks({ 'per_page': "20" });
                setRecommendWorks(recommendWorks);

                if (filterCatId) {
                    initialWorks = initialWorks.filter(work =>
                        work.categories.filter(cat => cat === filterCatId).length > 0
                    );
                }

                const sortedWorks = initialWorks.sort((a, b) =>
                    parseInt(b.acf.period.split(" - ")[0]) - parseInt(a.acf.period.split(" - ")[0])
                );

                setWorks(sortedWorks);
                setLoading(false);
            } catch (error) {
                console.error('作品取得エラー:', error);
                setWorks([]);
                setRecommendWorks([]);
                setLoading(false);
            }
        };

        fetchInitialWorks();
    }, [filterCatId, limited]);

    // 無限スクロール機能
    const loadMoreWorks = useCallback(async () => {
        if (loading || !hasMore) return;

        try {
            setLoading(true);
            let newWorks = await getWorks({ 'per_page': "20", 'page': page.toString() });

            // 配列でない場合や空配列の場合のチェック
            if (!Array.isArray(newWorks) || newWorks.length === 0) {
                setHasMore(false);
                setLoading(false);
                return;
            }

            if (!limited) {
                newWorks = newWorks.filter(work => !work.acf.limited);
            }

            if (filterCatId) {
                newWorks = newWorks.filter(work =>
                    work.categories.filter(cat => cat === filterCatId).length > 0
                );
            }

            const sortedNewWorks = newWorks.sort((a, b) =>
                parseInt(b.acf.period.split(" - ")[0]) - parseInt(a.acf.period.split(" - ")[0])
            );

            setWorks(prevWorks => [...prevWorks, ...sortedNewWorks]);
            setPage(prevPage => prevPage + 1);
            setLoading(false);
        } catch (error) {
            console.error('追加作品取得エラー:', error);
            setHasMore(false);
            setLoading(false);
        }
    }, [page, loading, hasMore, filterCatId, limited]);

    // スクロールイベントリスナー
    useEffect(() => {
        const handleScroll = () => {
            if ((window.innerHeight + window.scrollY) >= document.documentElement.scrollHeight - 200) {
                loadMoreWorks();
            }
        };

        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, [loadMoreWorks]);

    return (
        <section className="works">
            {!disableSlider && (
                <Slider works={recommendWorks} />
            )}
            {!disableSlider && (
                <Scrollate text='other projects' />
            )}
            <div className="others block md:grid grid-flow-dense md:grid-cols-2 lg:grid-cols-3 md:grid-rows-[200px] md:gap-5">
                {works.map(work => (
                    <WorkComponent key={work.id} limited={limited} work={work} />
                ))}
            </div>
            {loading && (
                <div className="text-center text-white py-4">
                    loading...
                </div>
            )}
        </section>
    );
};

export default Works; 