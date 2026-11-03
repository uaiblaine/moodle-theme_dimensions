# Shade nothing: a light chosen colour keeps an ink and an edge that do not read.
s/\@if contrast-ratio\(\$ground, \$readable\) < \$min \{/\@if contrast-ratio(\$ground, \$readable) < 1 {/;
