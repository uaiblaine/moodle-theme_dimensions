# Turn the root away again: the listing's module returns at category 0 whatever the page draws, so the
# root's search line is a form that reloads the page instead of searching live.
s/if \(isRootPage\(\) && !root\.querySelector\(SELECTORS\.toolbar\)\) \{/if (isRootPage()) {/;
