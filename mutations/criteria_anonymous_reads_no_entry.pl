# Read the remembered entry on the anonymous shell too: a session's favourites and state come back
# on the page that is meant to know nobody.
s/\$entry = \$anonymous \? null : remembered::for_category\(\$categoryid\);/\$entry = remembered::for_category(\$categoryid);/;
