# Draw the drawer as a plain division again: no navigation landmark.
s/<nav class="dimensions-drawer"/<div class="dimensions-drawer"/;
s/<\/nav>(\n\{\{#js\}\})/<\/div>$1/;
