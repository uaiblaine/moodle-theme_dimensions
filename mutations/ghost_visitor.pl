# Ghost visitors off a course too. The predicate can only answer "no" for somebody who is not
# logged in, so every anonymous request for an unlisted course would dead-end instead of going
# to the login page.
s/(if \(\$courseid <= 0 \|\| \$courseid == SITEID\) \{\n            return false;\n        \}\n)        if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{\n            return false;\n        \}\n/${1}/;
