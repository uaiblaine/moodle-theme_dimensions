# Read the remembered state on the anonymous shell too.
s/\$entry = \$anonymous \? null : remembered::for_category\(\$categoryid\);/\$entry = remembered::for_category(\$categoryid);/;
