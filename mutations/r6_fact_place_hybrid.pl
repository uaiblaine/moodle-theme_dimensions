# Keep the place rows to in-person offerings: a hybrid one names no venue or city.
s/if \(\$this->is_presencial\(\) \|\| \$this->is_hybrid\(\)\) \{/if (\$this->is_presencial()) {/;
