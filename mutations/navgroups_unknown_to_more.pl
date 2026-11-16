# Put a node the map does not know into a group of its own: alone it is drawn as a plain pill, with others as a
# group "Other", where D89 wants it always among More's own items.
s/if \(\$group === self::OTHER\) \{/if (false) {/;
