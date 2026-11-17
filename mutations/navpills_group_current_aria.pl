# Drop aria-current from the pill of the group that holds the current page: only the look and the dot remain.
s/(<summary class="dim-navpill\{\{#current\}\} is-selected\{\{\/current\}\}")\{\{#current\}\} aria-current="page"\{\{\/current\}\}/$1/g;
