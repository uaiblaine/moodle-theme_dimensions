# Offer "Search all areas" whoever reads the page: a visitor whose site keeps the root closed is sent to
# the login form, and the guest to core's course index, which ignores the term.
s/\$reachable = \$anonymous \? publicaccess::is_public\(0\) : isloggedin\(\) && !isguestuser\(\);/\$reachable = true;/;
