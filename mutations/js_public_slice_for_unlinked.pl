# Ask a public area as a slice and a merely listed one by its own id. The first is dropped (no panel:
# every press loads the page) and the second refused, so no visitor's panel ever opens in place.
s/if \(poster\.dataset\.titleurl === ''\) \{/if (poster.dataset.titleurl !== '') {/;
