# Draw a group's pill unselected whatever it holds: the group of the current page loses its look and its class.
s/(<details class="dim-navpill-group" data-group="\{\{key\}\}">\s*<summary class="dim-navpill)\{\{#current\}\} is-selected\{\{\/current\}\}/$1/;
