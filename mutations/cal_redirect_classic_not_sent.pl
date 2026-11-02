# Send the request that carries the classic marker too: the page's own link loops.
s/        if \(!empty\(\$request\[self::PARAM_CLASSIC\]\)\) \{/        if (false) {/;
