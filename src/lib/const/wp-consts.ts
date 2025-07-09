// WordPressサーバーのベースURL
// HTTPSおよびHTTPの両方で試行できるように設定
export const baseUrl = 'https://smitharia.shimizuyasushi.com';

// WordPressのREST APIエンドポイント
export const WPWorkUrl = baseUrl+'/wp-json/wp/v2/posts/';
export const WPCatUrl = baseUrl+'/wp-json/wp/v2/categories/';
export const WPMemberUrl = baseUrl+'/wp-json/wp/v2/member/';
export const WPPreferenceUrl = baseUrl+'/wp-json/wp/v2/preference/';

// APIリクエストのエラーハンドリング用関数
export const handleApiError = (error: any, endpoint: string) => {
  console.error(`Error fetching data from ${endpoint}:`, error);
  return null;
}; 