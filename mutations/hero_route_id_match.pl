# Trust the category id in the path: one category's image is then served under another category's context.
s/if \(\$categoryid !== \(int\) \$context->instanceid\) \{/if (false) {/;
