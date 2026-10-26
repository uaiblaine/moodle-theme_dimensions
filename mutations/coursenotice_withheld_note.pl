# A course set to public that the predicate refuses gets the "not in effect" note, not "login only".
s/        if \(\$state === self::STATE_PUBLIC\) \{/        if (false) {/;
