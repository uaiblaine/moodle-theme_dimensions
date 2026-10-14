# Serve whatever is in a badge area under any hash and any file name: a replaced crest then keeps
# answering to the old address for as long as a browser, a proxy or a scraper holds it.
s/if \(\$badge === null \|\| \$badge->get_contenthash\(\) !== \$hash \|\| \$badge->get_filename\(\) !== \$filename\) \{/if (\$badge === null) {/;
