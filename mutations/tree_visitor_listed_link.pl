# Put the own-page gate back on the flat tree: an entry the population marks not linkable (a
# visitor's merely listed child, which has no public page of its own) gets no address. That gate is
# the posters' and the accordion's, whose links open the child's page; the tree never leaves this
# one, so the visitor reads the name of a filter the server would honour and cannot press it.
# Anchored on the url's opening, the one call in tree() that writes the tree's own parameter.
s/('url' => )(links::showcase\(\n[ \t]*\$categoryid,\n[ \t]*\$params \+ \[criteria::PARAM_CAT => \$id\],)/$1empty(\$entry['linkable']) ? '' : $2/;
