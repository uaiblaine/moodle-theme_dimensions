# The logged-in shell stops building the strip: its pages vanish for every reader who is logged in
# while the anonymous page keeps them.
s/\$pages = category_pages::export\(\$categoryid\);/\$pages = null;/;
