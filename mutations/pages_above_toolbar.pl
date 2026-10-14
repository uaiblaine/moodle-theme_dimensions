# Draw the strip under the toolbar instead of above it: inside what the reader takes for the
# listing's own controls.
s/    \{\{#pages\}\}\n    \{\{> theme_dimensions\/category_pages \}\}\n    \{\{\/pages\}\}\n(    \{\{#toolbar\}\}\n    \{\{> theme_dimensions\/category_toolbar \}\}\n    \{\{\/toolbar\}\}\n)/$1    {{#pages}}\n    {{> theme_dimensions\/category_pages }}\n    {{\/pages}}\n/;
