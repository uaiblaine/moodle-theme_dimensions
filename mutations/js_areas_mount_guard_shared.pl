# Mount the posters' component on areas that carry no panel: it draws nothing and the pending never ends.
s/    if \(!areas \|\| !areas\.querySelector\(SELECTORS\.panel\)\) \{/    if (!areas) {/;
