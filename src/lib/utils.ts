import WPFetch, { type CategoryType, type WorkType as WpWorkType, type MemberType as WpMemberType } from './Wp';

// 型定義をエクスポート
export type { CategoryType, WpMemberType as MemberType };
export type WorkType = WpWorkType & {
  acf?: {
    thumbnail?: {
      url: string;
      alt?: string;
    };
    extend_column?: boolean;
    extend_row?: boolean;
    limited?: boolean;
    is_recommend?: boolean;
    period?: string;
  };
};

// CatTypeをCategoryTypeと互換性を持たせる
export interface CatType extends CategoryType {}

interface CategorizedType {
  works?: CatType[];
  studies?: CatType[];
  artists?: CatType[];
  [key: string]: CatType[] | undefined;
}

export async function getWorks(params?: Record<string, string | number>): Promise<WorkType[]> {
  return await WPFetch.getWorks(params) as unknown as WorkType[];
}

export async function getWork(id: string | number): Promise<WorkType | null> {
  const numId = typeof id === 'string' ? parseInt(id, 10) : id;
  const works = await WPFetch.getWorks({ id: numId });
  return works.length > 0 ? works[0] as unknown as WorkType : null;
}

export async function getRawCats(): Promise<CategoryType[]> {
  return await WPFetch.getCategories();
}

export async function getCats(): Promise<CategorizedType> {
  const cats = await WPFetch.getCategories();
  const result: CategorizedType = {};

  // カテゴリを種類ごとに分類
  cats.forEach(cat => {
    // ここでは例として、特定のカテゴリーを分類しています
    // 実際のプロジェクトに合わせて調整してください
    if (cat.slug.includes('work')) {
      if (!result.works) result.works = [];
      result.works.push(cat as CatType);
    } else if (cat.slug.includes('study')) {
      if (!result.studies) result.studies = [];
      result.studies.push(cat as CatType);
    } else if (cat.slug.includes('artist')) {
      if (!result.artists) result.artists = [];
      result.artists.push(cat as CatType);
    }
  });

  return result;
}

export function getCatsFromWork(work: WorkType, allCats: CategoryType[]): CategoryType[] {
  if (!work.categories || !Array.isArray(work.categories)) {
    return [];
  }

  return allCats.filter(cat => work.categories?.includes(cat.id));
}

// 画像パスをフィルター処理されたパスに変換する関数
export function convertImagePathToFiltered(path: string): string {
  if (!path) return '';
  
  // デバッグ用：変換前のパスをログ出力
  console.log('Original image path:', path);
  
  // URLからファイル名部分を抽出
  const urlParts = path.split('/');
  const fileName = urlParts[urlParts.length - 1];
  
  // WordPressの手動で生成したフィルター画像のプレフィックスを追加
  // 'filtered-'をプレフィックスとして使用
  let filteredFileName = 'filtered-' + fileName;
  
  // 元のURLでファイル名のみを置換
  urlParts[urlParts.length - 1] = filteredFileName;
  const filteredPath = urlParts.join('/');
  
  // デバッグ用：変換後のパスをログ出力
  console.log('Filtered image path:', filteredPath);
  
  return filteredPath;
}

// 追加：getPreference関数
export async function getPreference(): Promise<unknown> {
  return await WPFetch.getPreference();
}

// 追加：getMembers関数
export async function getMembers(): Promise<unknown> {
  return await WPFetch.getMembers();
} 