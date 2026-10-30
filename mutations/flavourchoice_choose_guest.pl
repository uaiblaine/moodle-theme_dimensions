# choose() records a look for the guest (in memory, but the menu would answer it).
# Anchored on the global before it, because menu() and applies() open with the same guard.
s/(global \$USER;\s+if \()!isloggedin\(\) \|\| isguestuser\(\) \|\| (\\core\\session\\manager::is_loggedinas\(\)\))/$1$2/;
