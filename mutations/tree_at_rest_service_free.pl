# Spend the tree-at-rest statement on the web service's flat request too: its answer carries no
# tree, and the visitor's client sends one on every keystroke, so each fetch pays a grouped
# statement for nothing. Anchored on the flag that marks the page's own request.
s/(\|\| )\$criteria->withposters\)/$1true)/;
