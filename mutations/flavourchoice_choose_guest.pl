# choose() records a look for the guest (in memory, but the menu would answer it).
# Anchored on what follows the guard, because applies() opens with the same guard.
s/if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{\n(\s*)return false;\n(\s*)\}\n(\s*)if \(\$flavourid/if (false) {\n$1return false;\n$2}\n$3if (\$flavourid/;
