# Hold the server's layer out of every page, not only under jsenabled: the no-JavaScript page loses its list.
s/    &\.jsenabled \.dim-cal:not\(\.dim-cal--mounted\) \{/    .dim-cal:not(.dim-cal--mounted) {/;
