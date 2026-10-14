# Let a visitor past the one refusal, so a category that is not public is looked up and
# served: a listed, an unlisted, a hidden and a nonexistent id stop answering alike.
s/if \(!publicaccess::is_public\(\$categoryid\)\) \{/if (false) {/;
