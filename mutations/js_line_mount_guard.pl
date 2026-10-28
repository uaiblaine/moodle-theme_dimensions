# Hand the component a line it cannot draw: with no field or no listbox its first commit replaces the
# server's line with nothing.
s/    if \(!line \|\| !line\.querySelector\(SELECTORS\.linefield\) \|\| !line\.querySelector\(SELECTORS\.listbox\) \|\| !form\.id\) \{/    if (!line) {/;
