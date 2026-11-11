# Build the staff list over the whole clamped category instead of the request's matches: capped in
# core order, it withholds a match past the fiftieth course the viewer has standing at.
s/(\$state = discoverability::state_sql\('c'\);\n\s+\$where = "c\.category \{\$catsql\} AND c\.id <> :siteid";\n\s+)if \(\$term !== ''\)/${1}if (false)/;
