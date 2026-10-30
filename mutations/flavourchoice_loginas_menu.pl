# Somebody logged in as another user is offered that person's looks.
s/(\?array \{\s+if \(!isloggedin\(\) \|\| isguestuser\(\)) \|\| \\core\\session\\manager::is_loggedinas\(\)\)/$1)/;
