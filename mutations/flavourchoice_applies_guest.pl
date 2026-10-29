# prime() runs for a visitor and the guest: their first page purges what Boost Union resolved.
# Anchored on what follows the guard, because choose() opens with the same guard.
s/if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{\n(\s*)return false;\n(\s*)\}\n\n(\s*)\$preview/if (false) {\n$1return false;\n$2}\n\n$3\$preview/;
