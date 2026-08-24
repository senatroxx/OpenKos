type TranslationCatalog = Record<string, unknown>;

let messages: TranslationCatalog = {};
let fallbackMessages: TranslationCatalog = {};

export function setTranslations(
    current: TranslationCatalog | null | undefined,
    fallback: TranslationCatalog | null | undefined,
): void {
    messages = current ?? {};
    fallbackMessages = fallback ?? {};
}

function interpolate(
    value: string,
    replacements: Record<string, string | number>,
): string {
    return value.replace(/:([A-Za-z0-9_]+)/g, (_, key: string) =>
        String(replacements[key] ?? `:${key}`),
    );
}

export function t(
    key: string,
    replacements: Record<string, string | number> = {},
): string {
    const value = messages[key] ?? fallbackMessages[key] ?? key;
    const translation = typeof value === 'string' ? value : key;

    return interpolate(translation, replacements);
}
