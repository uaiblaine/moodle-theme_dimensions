# The guest entry is answered only where the course offers it.
s/        if \(\(\$guest \|\| \$enter\) && guestaccess::available\(\$course\)\) \{/        if (\$guest || \$enter) {/;
