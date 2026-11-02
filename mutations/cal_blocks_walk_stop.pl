# Walk the whole lookahead after the count is reached.
s/            if \(\$max > 0 && count\(\$kept\) >= \$max\) \{/            if (false) {/;
