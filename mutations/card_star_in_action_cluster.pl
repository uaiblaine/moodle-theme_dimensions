# Move the star out of the action cluster, where it is the last child, and back beside the media band.
# Applied with -0777: the block spans lines.
s/(    <\/div>\n)(    <div class="dim-card__body">)(.*?)(\n\s*\{\{#showstar\}\}.*?\{\{\/showstar\}\})/$1$4\n$2$3/s;
