# Send a visitor or the guest to a page that sends them back.
s/        if \(\$account <= 0 \|\| !\$gregorian\) \{/        if (!\$gregorian) {/;
