# The guest and visitors get a Look menu.
# Anchored on the signature, because choose() opens with the same guard.
s/(\?array \{\s+if \()!isloggedin\(\) \|\| isguestuser\(\) \|\| (\\core\\session\\manager::is_loggedinas\(\)\))/$1$2/;
