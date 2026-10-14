# Draw auth_loginsteps' Security category where its plugin added it, after core's cards.
s~\$categories = array_merge\(\$first, \$categories\);~\$categories = array_merge(\$categories, \$first);~;
