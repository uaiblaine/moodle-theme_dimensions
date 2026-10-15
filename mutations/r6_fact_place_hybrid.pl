# Keep the place rows to in-person offerings: a hybrid one names no venue or city.
s/if \(\$this->is_inperson\(\) \|\| \$this->is_hybrid\(\)\) \{/if (\$this->is_inperson()) {/;
