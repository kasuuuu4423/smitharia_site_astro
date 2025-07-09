import { baseUrl as _baseUrl, WPWorkUrl, WPCatUrl, WPMemberUrl, WPPreferenceUrl } from "./const/wp-consts"; 

export interface WPParamType {
    id?: number;
    categories?: string;
    per_page?: string;
    page?: string;
    orderby?: string;
    filter?: string;
}

export interface WorkType {
    id: number;
    title: string;
    content: string;
    permalink: string;
    date: Date;
    categories?: number[];
    thumbnail?: string;
    thumbnailFull?: string;
    featuredImage?: string;
    mediaDetails?: {
        width: number;
        height: number;
        sizes: {
            [key: string]: {
                width: number;
                height: number;
                file: string;
                mime_type: string;
                source_url: string;
            }
        }
    };
    acf: Record<string, unknown>;
}

export interface CategoryType {
    id: number;
    count: number;
    name: string;
    description: string;
    slug: string;
    parent: number;
    children?: CategoryType[];
    parent_obj?: CategoryType;
}

export interface MemberType {
    id: number;
    title: string;
    content: string;
    position: string;
    name_ja: string;
    name_en: string;
    hp_url: string;
    twitter_url: string;
    other_url: string;
    order: number;
    thumbnail?: string;
    thumbnailFull?: string;
}

export interface PreferenceType {
    id: number;
    title: string;
    gallery?: unknown;
    logo_img?: string;
    footer_text?: string;
    acf?: Record<string, unknown>;
}

export interface WPAPIResponse {
    data: unknown;
    status: number;
    statusText: string;
}

/**
 * WordPressデータを取得するためのクラス
 */
export default class WPFetch {
    static async fetchData(url: string): Promise<WPAPIResponse> {
        try {
            const response = await fetch(url);
            return {
                data: await response.json(),
                status: response.status,
                statusText: response.statusText
            };
        } catch (error) {
            console.error(`Error in fetchData: ${error}`);
            return {
                data: null,
                status: 500,
                statusText: "Internal Error"
            };
        }
    }

    static async getWorks(params: WPParamType = {}): Promise<WorkType[]> {
        let url = WPWorkUrl;
        const paramStr = Object.entries(params)
            .map(([key, value]) => `${key}=${value}`)
            .join('&');
        
        if (paramStr) {
            url += `?${paramStr}&_embed`;
        } else {
            url += `?_embed`;
        }

        const response = await this.fetchData(url);
        if (response.status !== 200) {
            console.error(`Error: ${response.statusText}`);
            return [];
        }

        return this.parseWorksResponse(response.data);
    }

    static parseWorksResponse(data: unknown): WorkType[] {
        if (!Array.isArray(data)) return [];

        return data.map(item => {
            const work: WorkType = {
                id: item.id,
                title: item.title.rendered,
                content: item.content.rendered,
                permalink: item.link,
                date: new Date(item.date),
                categories: item.categories,
                acf: item.acf || {}
            };

            // ACFフィールドからサムネイル情報を確認
            if (item.acf && item.acf.thumbnail) {
                // ここでacfのデータをコンソールに出力して確認 (開発環境のみ)
                console.log('ACF Thumbnail data:', item.acf.thumbnail);
            }

            // サムネイル情報の抽出
            if (item._embedded && item._embedded['wp:featuredmedia'] && item._embedded['wp:featuredmedia'][0]) {
                const media = item._embedded['wp:featuredmedia'][0];
                work.thumbnail = media.media_details.sizes.medium?.source_url || '';
                work.thumbnailFull = media.source_url || '';
                work.featuredImage = media.source_url || '';
                work.mediaDetails = media.media_details;
            }

            return work;
        });
    }

    static async getCategories(): Promise<CategoryType[]> {
        const url = `${WPCatUrl}?per_page=100`;
        const response = await this.fetchData(url);
        
        if (response.status !== 200) {
            console.error(`Error: ${response.statusText}`);
            return [];
        }

        const categories = this.parseCategoriesResponse(response.data);
        // カテゴリーツリーの再構築
        const reconstructCats = this.buildCategoryTree(categories);
        return reconstructCats;
    }

    static parseCategoriesResponse(data: unknown): CategoryType[] {
        if (!Array.isArray(data)) return [];

        return data.map(item => ({
            id: item.id,
            count: item.count,
            name: item.name,
            description: item.description,
            slug: item.slug,
            parent: item.parent
        }));
    }

    static buildCategoryTree(categories: CategoryType[]): CategoryType[] {
        // 親カテゴリ（parent=0）を集める
        const rootCategories = categories.filter(cat => cat.parent === 0);
        
        // 子カテゴリを親カテゴリに割り当てる
        return rootCategories.map(rootCat => {
            const childrenCategories = categories.filter(cat => cat.parent === rootCat.id);
            
            // 子カテゴリに親オブジェクトの参照を追加
            const childrenWithParent = childrenCategories.map(child => {
                return {
                    ...child,
                    parent_obj: rootCat
                };
            });
            
            return {
                ...rootCat,
                children: childrenWithParent
            };
        });
    }

    static async getMembers(): Promise<MemberType[]> {
        const url = `${WPMemberUrl}?per_page=100&_embed`;
        const response = await this.fetchData(url);
        
        if (response.status !== 200) {
            console.error(`Error: ${response.statusText}`);
            return [];
        }

        return this.parseMembersResponse(response.data);
    }

    static parseMembersResponse(data: unknown): MemberType[] {
        if (!Array.isArray(data)) return [];

        const members = data.map(item => {
            const member: MemberType = {
                id: item.id,
                title: item.title.rendered,
                content: item.content.rendered,
                position: item.acf?.position || '',
                name_ja: item.acf?.name_ja || '',
                name_en: item.acf?.name_en || '',
                hp_url: item.acf?.hp_url || '',
                twitter_url: item.acf?.twitter_url || '',
                other_url: item.acf?.other_url || '',
                order: parseInt(item.acf?.order || '0', 10)
            };

            // サムネイル情報の抽出
            if (item._embedded && item._embedded['wp:featuredmedia'] && item._embedded['wp:featuredmedia'][0]) {
                const media = item._embedded['wp:featuredmedia'][0];
                member.thumbnail = media.media_details.sizes.medium?.source_url || '';
                member.thumbnailFull = media.source_url || '';
            }

            return member;
        });

        // order値でソート
        return members.sort((a, b) => a.order - b.order);
    }

    static async getPreference(): Promise<PreferenceType | null> {
        const _directory = 'preference';
        const url = `${WPPreferenceUrl}?per_page=1&_embed`;
        const response = await this.fetchData(url);
        
        if (response.status !== 200 || !Array.isArray(response.data) || response.data.length === 0) {
            console.error(`Error: ${response.statusText}`);
            return null;
        }

        const item = response.data[0];
        const preference: PreferenceType = {
            id: item.id,
            title: item.title.rendered,
            gallery: item.acf?.gallery || [],
            logo_img: item.acf?.logo_img?.url || '',
            footer_text: item.acf?.footer_text || '',
            acf: item.acf
        };

        return preference;
    }
} 