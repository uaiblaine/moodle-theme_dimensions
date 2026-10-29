# The guest and visitors get a Look menu.
s/if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{\n(\s*)return null;/if (false) {\n$1return null;/;
