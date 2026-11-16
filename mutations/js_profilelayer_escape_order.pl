# Close the layer on Escape even inside a sub-view: the sub-view is never left first.
s/if \(current\(\) !== state\.layer\.querySelector\(SELECTORS\.MAIN\)\) \{/if (false) {/;
