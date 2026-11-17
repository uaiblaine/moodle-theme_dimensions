# Give the plain pill's icon its destination's text as alternative text: the icon is then announced beside the
# text it stands next to, and carries a title.
s/<span class="dim-navpill__icon">\{\{#pix\}\} \{\{icon\.key\}\}, \{\{icon\.component\}\} \{\{\/pix\}\}/<span class="dim-navpill__icon">{{#pix}} {{icon.key}}, {{icon.component}}, {{text}} {{\/pix}}/;
