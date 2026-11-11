# Choose the viewer's courses in clamped categories past the cap without the request's term: the
# first fifty in core order, whatever they are called, withhold a match the PHP cut shows.
s/('rlc'\);\n\s+\$where = "c\.category \{\$catsql\} AND c\.id <> :siteid";\n\s+)if \(\$term !== ''\)/${1}if (false)/;
