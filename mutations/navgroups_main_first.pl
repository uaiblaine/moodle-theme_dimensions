# Treat the category page's own node as any other: "Category" leaves the head of the row for More.
s/if \(\$node->key === self::MAIN\) \{/if (false) {/;
