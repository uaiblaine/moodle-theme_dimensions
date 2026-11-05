# A course's popover opens on its bar though the reader folded its trail while the scroll ran.
s/if \(bar && !bar\.closest\('\[hidden\]'\)\) \{/if (bar) {/;
