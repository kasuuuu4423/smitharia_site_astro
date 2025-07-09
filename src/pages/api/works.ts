import type { APIRoute } from 'astro';
import { getWorksPage, getWorksHeader } from '../../lib/WP';

export const GET: APIRoute = async ({ url }) => {
    try {
        const params = new URLSearchParams(url.search);
        const page = parseInt(params.get('page') || '1');
        const perPage = params.get('per_page') || '20';
        const categories = params.get('categories');
        
        console.log('API request:', { page, perPage, categories });
        
        const worksParams: Record<string, string> = {
            per_page: perPage,
            orderby: 'date',
            order: 'desc'
        };
        
        if (categories) {
            worksParams.categories = categories;
        }
        
        // 作品データとヘッダー情報を取得（ヘッダー情報は1ページ目で十分）
        const works = await getWorksPage(page, worksParams);
        const headers = await getWorksHeader({ ...worksParams, page: "1" }); // ヘッダー情報は1ページ目から取得
        
        console.log('API fetched:', { worksCount: works.length, headers });
        
        // APIレベルでソート済みなので、クライアント側のソートは不要
        const sortedWorks = works;
        
        const response = {
            works: sortedWorks,
            meta: {
                total: parseInt(headers.x_wp_total || '0'),
                totalPages: parseInt(headers.x_wp_totalpages || '1'),
                currentPage: page,
                perPage: parseInt(perPage)
            }
        };
        
        console.log('API response:', response);
        
        return new Response(JSON.stringify(response), {
            status: 200,
            headers: {
                'Content-Type': 'application/json'
            }
        });
    } catch (error) {
        console.error('Works API エラー:', error);
        return new Response(JSON.stringify({ error: 'Failed to fetch works' }), {
            status: 500,
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }
}; 