# Leave the category's default out of the addresses of a reader who has a remembered state: the
# bare address they mint is then read back as that state, not as the page on screen.
s/if \(\$this->remembered\) \{/if (false) {/;
