# Mount the component on a page with no panel: it draws nothing there, never says it is drawn, and the
# mount's pending never ends.
s/    if \(!areas \|\| !areas\.querySelector\(SELECTORS\.panel\)\) \{/    if (!areas) {/;
