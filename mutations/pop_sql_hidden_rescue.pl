# Let the SQL-cut page ignore core's visibility: a hidden course is listed to a viewer who is
# neither enrolled in it nor allowed to see hidden courses, whichever branch admitted it.
s/\. " AND \(c\.visible = 1 OR c\.id \{\$rsql\}\)";/. " AND (1 = 1 OR c.id {\$rsql})";/;
