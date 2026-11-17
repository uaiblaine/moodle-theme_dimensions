# Draw the row over a plugin's own secondary navigation: its tabs are replaced by the category's row.
s/if \(get_class\(\$page->secondarynav\) !== secondary::class\) \{/if (false) {/;
