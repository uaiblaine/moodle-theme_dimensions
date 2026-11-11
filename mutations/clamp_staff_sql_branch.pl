# Drop the staff list from the SQL cut's predicate: a course of a category the plugin alone clamps,
# which the PHP cut lists to a viewer with staff standing at it, is withheld in the SQL cut again.
s/ OR c\.id \{\$lsql\} OR c\.id \{\$ssql\}\)"/ OR c.id {\$lsql})"/;
