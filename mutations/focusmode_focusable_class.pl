# Drop the class every page that can switch carries: the focus-only parts are no longer hidden in the
# ordinary skin, and the entry button loses its pill.
s/\n[ \t]*\$additionalclasses\[\] = 'dimensions-focusable';(?=\n)//;
