# Ignore the reader's choice of core's calendar for the session.
s/        if \(\(\$session->\{self::SESSION_FLAG\} \?\? null\) === \$account\) \{/        if (false) {/;
