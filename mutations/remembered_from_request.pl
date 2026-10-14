# Build the page's request without the reader's remembered state.
s/\$entry = \$anonymous \? null : remembered::for_category\(\$categoryid\);/\$entry = null;/;
