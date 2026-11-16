# Count a plain destination as the only thing a row needs to be drawn for, so "Category" alone draws a row (D87).
s/if \(\$unit\['kind'\] !== self::KIND_ITEM \|\| \$unit\['key'\] !== self::MAIN\) \{/if (true) {/;
