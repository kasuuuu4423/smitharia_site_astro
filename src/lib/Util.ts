export function decodeHtmlEscape(str: string): string {
    return str
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#8216;/g, "'")
        .replace(/&#8211;/g, "-")
        .replace(/&#039;/g, '\'')
        .replace(/&#044;/g, ',')
        .replace(/&amp;/g, '&');
}