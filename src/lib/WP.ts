import { WPWorkUrl, WPCatUrl, WPMemberUrl } from "./const/wp_consts"; 

export interface WPParamType {
    id?: number,
    categories?: string,
    per_page?: string,
    page?: string,
    orderby?: string,
    filter?: string,
};

export interface Work {
    acf: {
        credit: string,
        description: string,
        period: string,
        thumbnail: {
            id: number,
            url: string,
            alt: string,
            sizes: {
                thumbnail: string,
                medium: string,
                medium_large: string,
                large: string,
            },
        },
        extend_column: boolean,
        extend_row: boolean,
        is_recommend: boolean,
    },
    id: number,
    categories: number[],
    content: {
        rendered: string,
    },
    title: {
        rendered: string,
    }
};


export interface CatType {
    id: number,
    name: string,
    parent: number,
    slug: string,
}

export interface ReconstructCatsType {
    works: CatType[],
    studies: CatType[],
    artists: CatType[],
};

export interface MemberType {
    acf: {
        name: string,
        english_name: string,
        position: string,
        english_position: string,
        heading_position: string,
        bio: string,
        english_bio: string,
        pic: string,
    },
    id: number,
};

export async function getCats(): Promise<ReconstructCatsType> {
    const response = await fetch(
        WPCatUrl + "?per_page=100&orderby=id&order=asc",
        {
        headers: {
            "Content-Type": "application/json",
        },
        }
        
    );
    const data = (await response.json()) as CatType[];
    const cats = reconstructCats(data);
    return cats;
}

function reconstructCats(rawCats: CatType[]): ReconstructCatsType{
    //const cats = {} as {[key: string]: CatType[]};
    const parents: CatType[] = rawCats.filter(cat=>cat.parent===0);
    let reconstructCats: ReconstructCatsType = {
        works: [],
        studies: [],
        artists: [],
    } as ReconstructCatsType;
    parents.forEach((parent)=>{
        if(parent.name === "works" || parent.name === "studies" || parent.name === "artists") {
            reconstructCats[parent.name] = rawCats.filter(cat=>cat.parent === parent.id);
        }
    });
    return reconstructCats as ReconstructCatsType;
}


export async function getWork(id: number): Promise<Work>{
    const u = new URL(WPWorkUrl+id+"/");
    const worksRes = await fetch(u);
    const work: Work = await worksRes.json();
    return work;
}

export async function getWorks(
    params: WPParamType = {} as WPParamType
): Promise<Work[]>{
    const u = new URL(WPWorkUrl);
    const p: {[key: string]: string} = deleteEmptyParam(params);
    u.search = objectToQueryString(p);
    const worksRes = await fetch(u);
    const works: Work[] = await worksRes.json();
    return works;
}

export async function getCatById(id: number): Promise<CatType>{
    const u = new URL(WPCatUrl+id+"/");
    const p: {[key: string]: string} = {
        'per_page': "100",
    };
    u.search = objectToQueryString(p);
    const catRaw = await fetch(u);
    const cat: CatType = await catRaw.json();
    return cat;
}

export function getCatsFromWork(work: Work, cats: CatType[]): CatType[]{
    const workCats = work.categories.map(id=>cats.find(cat=>cat.id===id));
    return workCats as CatType[];
}

export async function getRawCats(): Promise<CatType[]>{
    const u = new URL(WPCatUrl);
    const p: {[key: string]: string} = {
        'per_page': "100",
    };
    u.search = objectToQueryString(p);
    const catsRaw = await fetch(u);
    const cats: CatType[] = await catsRaw.json();
    return cats;
}

export function isMatchCat(selectedCats: string[], workCats: string[]){
    let tmp = [];
    for(let cat of selectedCats){
        if(workCats.includes(cat)){
            tmp.push(cat);
        }
    }
    return selectedCats.length === tmp.length;
}

export async function getMembers(): Promise<MemberType[]>{
    const u = new URL(WPMemberUrl);
    const p: {[key: string]: string} = {
        'per_page': "100",
    };
    u.search = objectToQueryString(p);
    const membersRaw = await fetch(u);
    const members = await membersRaw.json();
    return members;
}

function deleteEmptyParam(params: WPParamType): {[key: string]: string}{
    const p: WPParamType = params;
    if(params.categories === "") delete p.categories;
    if(params.per_page === "") delete p.per_page;
    if(params.orderby === "") delete p.orderby;
    if(params.page === "") delete p.page;
    return p as {[key: string]: string};
}

function objectToQueryString(obj: {[key: string]: string}): string{
    let queryString = "";
    Object.keys(obj).forEach(key => {
        queryString += key + "=" + obj[key] + "&";
    });
    return queryString;
}

export function convertImagePathToFiltered(imagePath: string): string{
    const filename = imagePath.split("/").pop();
    const directory = imagePath.split("/").slice(0, -1).join("/");
    return directory + "/filtered_" + filename;
}