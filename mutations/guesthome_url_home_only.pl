# Only a URL home page is replaced; a numeric one is core's to resolve.
s/        if \(!self::is_url\(\(string\) \(\$CFG->defaulthomepage \?\? ''\)\)\) \{/        if (false) {/;
