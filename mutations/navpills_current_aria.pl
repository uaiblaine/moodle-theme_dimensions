# Drop aria-current from the current plain pill: the dot is then the only sign of the current page.
s/(data-key="\{\{key\}\}")\{\{#current\}\} aria-current="page"\{\{\/current\}\}(><span class="dim-navpill__icon">)/$1$2/;
