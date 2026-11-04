# Never run the multilang filter: a visitor reads every language of a name joined.
s/        if \(self::multilang_on\(\)\) \{/        if (false) {/;
