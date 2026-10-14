# Ghost visitors over a category too. The predicate can only answer "no" for somebody who is not
# logged in, so every anonymous request for a category page is sent to the not-available page -
# stranding people who would be eligible the moment they signed in, and taking the front page's
# own category links with it.
s/(if \(\$categoryid <= 0\) \{\n            return false;\n        \}\n)        if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{\n            return false;\n        \}\n/${1}/;
