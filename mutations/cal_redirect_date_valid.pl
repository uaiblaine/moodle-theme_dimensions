# Take any positive day, month and year for a date, 31 November included.
s/        if \(\$y <= 9999 && checkdate\(\$m, \$d, \$y\)\) \{/        if (\$y <= 9999 \&\& \$d > 0 \&\& \$m > 0 \&\& \$y > 0) {/;
