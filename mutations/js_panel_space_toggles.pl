# Let Space on a poster announced as a button do nothing: the page scrolls and the panel stays shut.
s/if \(event\.key !== ' ' \|\| event\.repeat \|\| !mode\.links\) \{/if (event.key !== 'Space' || event.repeat || !mode.links) {/;
