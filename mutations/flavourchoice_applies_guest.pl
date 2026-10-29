# prime() runs for a visitor and the guest: their first page purges what Boost Union resolved.
s/if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{\n(\s*)return false;/if (false) {\n$1return false;/;
