# Never read the path of a node core keyed by position: "Upload courses" falls out of its group into More.
s/if \(is_int\(\$key\)\) \{/if (false) {/;
