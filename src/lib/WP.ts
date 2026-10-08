import { baseUrl, WPWorkUrl, WPCatUrl, WPMemberUrl, WPPreferenceUrl } from './const/wp_consts';

export interface WPParamType {
  id?: number;
  categories?: string;
  per_page?: string;
  page?: string;
  orderby?: string;
  order?: string;
  filter?: string;
  meta_key?: string;
  meta_value?: string;
  meta_compare?: string;
  is_recommend?: string;
  limited?: 'include' | 'exclude' | 'only';
}

interface WPImage {
  id: number;
  url: string;
  alt: string;
  sizes: {
    thumbnail: string;
    medium: string;
    medium_large: string;
    large: string;
  };
}

export interface Work {
  acf: {
    credit: string;
    description: string;
    period: string;
    thumbnail: WPImage;
    extend_column: boolean;
    extend_row: boolean;
    is_recommend: boolean;
    limited: boolean;
  };
  id: number;
  categories: number[];
  content: {
    rendered: string;
  };
  title: {
    rendered: string;
  };
}

export interface CatType {
  id: number;
  name: string;
  parent: number;
  slug: string;
}

export interface ReconstructCatsType {
  works: CatType[];
  studies: CatType[];
  artists: CatType[];
}

export interface MemberType {
  acf: {
    name: string;
    english_name: string;
    position: string;
    english_position: string;
    heading_position: string;
    bio: string;
    english_bio: string;
    pic: string;
    limited_order: number;
  };
  id: number;
}

export interface Preference {
  acf: {
    about_image: WPImage;
    about_description: string;
  };
}

export async function getCats(): Promise<ReconstructCatsType> {
  const response = await fetch(WPCatUrl + '?per_page=100&orderby=id&order=asc', {
    headers: {
      'Content-Type': 'application/json',
    },
  });
  const data = (await response.json()) as CatType[];
  const cats = reconstructCats(data);
  return cats;
}

function reconstructCats(rawCats: CatType[]): ReconstructCatsType {
  // const cats = {} as {[key: string]: CatType[]};
  const parents: CatType[] = rawCats.filter((cat) => cat.parent === 0);
  const reconstructCats: ReconstructCatsType = {
    works: [],
    studies: [],
    artists: [],
  } as ReconstructCatsType;
  parents.forEach((parent) => {
    if (parent.name === 'works' || parent.name === 'studies' || parent.name === 'artists') {
      reconstructCats[parent.name] = rawCats.filter((cat) => cat.parent === parent.id);
    }
  });
  return reconstructCats as ReconstructCatsType;
}

export async function getWork(id: number): Promise<Work> {
  const u = new URL(WPWorkUrl + id + '/');
  const worksRes = await fetch(u);
  const work: Work = await worksRes.json();
  return work;
}

export async function getWorks(params: WPParamType = {} as WPParamType): Promise<Work[]> {
  try {
    const u = new URL(WPWorkUrl);
    const p: { [key: string]: string } = deleteEmptyParam(params);
    u.search = objectToQueryString(p);
    const worksRes = await fetch(u);

    if (!worksRes.ok) {
      console.error(`WordPress API error: ${worksRes.status} ${worksRes.statusText}`);
      return [];
    }

    const works: Work[] = await worksRes.json();

    if (!Array.isArray(works)) {
      console.error('WordPress API returned non-array response:', works);
      return [];
    }

    return works;
  } catch (error) {
    console.error('Error fetching works:', error);
    return [];
  }
}

export async function getCatById(id: number): Promise<CatType> {
  const u = new URL(WPCatUrl + id + '/');
  const p: { [key: string]: string } = {
    per_page: '100',
  };
  u.search = objectToQueryString(p);
  const catRaw = await fetch(u);
  const cat: CatType = await catRaw.json();
  return cat;
}

export function getCatsFromWork(work: Work, cats: CatType[]): CatType[] {
  const workCats = work.categories.map((id) => cats.find((cat) => cat.id === id));
  return workCats as CatType[];
}

export async function getRawCats(): Promise<CatType[]> {
  const u = new URL(WPCatUrl);
  const p: { [key: string]: string } = {
    per_page: '100',
  };
  u.search = objectToQueryString(p);
  const catsRaw = await fetch(u);
  const cats: CatType[] = await catsRaw.json();
  return cats;
}

export function isMatchCat(selectedCats: string[], workCats: string[]) {
  const tmp = [];
  for (const cat of selectedCats) {
    if (workCats.includes(cat)) {
      tmp.push(cat);
    }
  }
  return selectedCats.length === tmp.length;
}

export async function getMembers(): Promise<MemberType[]> {
  const u = new URL(WPMemberUrl);
  const p: { [key: string]: string } = {
    per_page: '100',
  };
  u.search = objectToQueryString(p);
  const membersRaw = await fetch(u);
  const members = await membersRaw.json();
  return members;
}

export async function getPreference(): Promise<Preference> {
  const u = new URL(WPPreferenceUrl);
  const prefRes = await fetch(u);
  const preference: Preference[] = await prefRes.json();
  return preference[0];
}

function deleteEmptyParam(params: WPParamType): { [key: string]: string } {
  return Object.fromEntries(
    Object.entries(params)
      .filter(([, value]) => value !== '' && value !== undefined)
      .map(([key, value]) => [key, String(value)])
  );
}

function objectToQueryString(obj: { [key: string]: string }): string {
  return new URLSearchParams(obj).toString();
}

export async function getRecommendedWorks(
  params: WPParamType = {} as WPParamType
): Promise<Work[]> {
  try {
    const recommendedParams = {
      ...params,
      is_recommend: 'true',
    };
    return await getWorks(recommendedParams);
  } catch (error) {
    console.error('Error fetching recommended works:', error);
    return [];
  }
}

export function convertImagePathToFiltered(imagePath: string): string {
  return getFilteredImagePaths(imagePath)[0] ?? imagePath;
}

export function getFilteredImagePaths(imagePath: string): string[] {
  if (!imagePath) return [];

  try {
    const sourceUrl = new URL(imagePath, baseUrl);
    const uploadsMarker = '/wp-content/uploads/';
    const markerIndex = sourceUrl.pathname.indexOf(uploadsMarker);

    if (markerIndex === -1) return [imagePath];

    const relativePath = sourceUrl.pathname.slice(markerIndex + uploadsMarker.length);
    const pathParts = relativePath.split('/').filter(Boolean);
    const filename = pathParts.pop();
    if (!filename) return [imagePath];

    const relativeDirectory = pathParts.join('/');
    const modernPath = `${uploadsMarker}filtered/${relativeDirectory ? `${relativeDirectory}/` : ''}filtered-${filename}`;
    const legacyPath = `${uploadsMarker}filtered/filtered_${filename}`;

    return Array.from(
      new Set([
        new URL(modernPath, sourceUrl.origin).toString(),
        new URL(legacyPath, sourceUrl.origin).toString(),
        sourceUrl.toString(),
      ])
    );
  } catch {
    return [imagePath];
  }
}
