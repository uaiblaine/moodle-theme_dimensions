# Hand the failure's own words to the fallback.
s/const failed = \(page: number, append: boolean\): void => \{\n    fallBack\(fallbackUrl\(page, append\)\);/const failed = (page: number, append: boolean, error: unknown): void => {\n    fallBack(fallbackUrl(page, append), String((error as Error).message));/;
