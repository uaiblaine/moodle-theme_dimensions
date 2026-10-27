# Put the eager covers on the direct courses of a grouped page too: they sit under the posters, below
# the first row, and would be asked for at once with the posters that are the first row.
s/if \(\$this->firstpaint && \$areas === null && \$this->result->page === 0\) \{/if (\$this->firstpaint && \$this->result->page === 0) {/;
