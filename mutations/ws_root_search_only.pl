# Let the web service answer the root with a filter, an organisation or a page it does not read: the
# answer then silently ignores what the request asked for.
s/if \(\$unused\) \{/if (false) {/;
