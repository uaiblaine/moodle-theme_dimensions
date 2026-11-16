# Take the user button over without the layer found: a page without the layer loses Boost Union's dropdown.
s/if \(!layer \|\| !backdrop \|\| !toggle \|\| layer\.classList\.contains\(CLASSES\.ARMED\)\) \{/if (!toggle) {/;
