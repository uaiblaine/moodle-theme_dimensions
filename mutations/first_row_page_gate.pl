# Put the eager covers on every page of a flat listing, not on the first: a page appended by "Load more"
# or opened past the first then asks for three covers below the fold at once, ahead of everything the
# reader is looking at.
s/if \(\$this->firstpaint && \$areas === null && \$this->result->page === 0\) \{/if (\$this->firstpaint && \$areas === null) {/;
