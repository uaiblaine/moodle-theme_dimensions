# Count a flag whatever account wrote it.
s/        if \(\(\$session->\{self::SESSION_FLAG\} \?\? null\) === \$account\) \{/        if ((\$session->{self::SESSION_FLAG} ?? null) !== null) {/;
