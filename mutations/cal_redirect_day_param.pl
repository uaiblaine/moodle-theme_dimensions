# Send the day view without the day, so the drawer stays closed.
s/        if \(\(string\) \(\$request\['view'\] \?\? ''\) === self::VIEW_DAY\) \{/        if (false) {/;
