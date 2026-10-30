# Somebody logged in as another user records a look, and moves a cohort, for that person.
s/(global \$USER;\s+if \(!isloggedin\(\) \|\| isguestuser\(\)) \|\| \\core\\session\\manager::is_loggedinas\(\)\)/$1)/;
