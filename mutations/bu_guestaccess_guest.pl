# Boost Union's guest access hint is for a reader in the course as a guest.
s/        if \(!\$notice->landing \|\| !is_guest\(\$context, \$USER->id\)\) \{/        if (!\$notice->landing) {/;
