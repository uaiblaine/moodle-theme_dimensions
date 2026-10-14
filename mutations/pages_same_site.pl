# Drop the slash of the same-site rule: an address on "<wwwroot>evil" - another site whose name
# starts with this one's - then passes as this site's own.
s/!str_starts_with\(\$url, \$CFG->wwwroot \. '\/'\)/!str_starts_with(\$url, \$CFG->wwwroot)/;
