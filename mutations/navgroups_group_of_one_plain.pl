# Draw a group of one destination as a group: a pill that opens a menu of one item instead of the item itself.
s/if \(count\(\$items\) === 1\) \{/if (false) {/;
