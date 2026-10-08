export function getBuildHeaders(): Record<string, string> {
  const serviceKey = process.env.SMITHARIA_SERVICE_KEY;
  if (!serviceKey || serviceKey.length < 32) {
    throw new Error('限定ページのビルドにはSMITHARIA_SERVICE_KEY（32文字以上）が必要です。');
  }
  return { 'X-Smitharia-Service-Key': serviceKey };
}
