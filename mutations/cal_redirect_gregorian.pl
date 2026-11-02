# Send a non-Gregorian viewer to a page that sends them back.
s/        if \(\$account <= 0 \|\| !\$gregorian\) \{/        if (\$account <= 0) {/;
