# Name the first eager CARD the first paint's first, cover or not: a card without a cover to fetch asks for nothing.
s/if \(\$card->hasimage && !str_starts_with\(\(string\) \$card->imageurl, .data:.\)\) \{/if (true) {/;
