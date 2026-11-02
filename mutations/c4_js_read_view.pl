# Read any view an address names as the week.
s/if \(params\.get\(VIEW_PARAM\) !== 'week'\) \{/if (!params.has(VIEW_PARAM)) {/;
