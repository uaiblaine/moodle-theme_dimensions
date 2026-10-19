# Decide while the bar is busy: the bar swaps or leaves under an open menu, a drawer or the keyboard's focus.
s/if \(held\) \{/if (!held && held) {/;
