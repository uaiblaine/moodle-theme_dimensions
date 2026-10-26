# Take the first paint away from the visitor's listing (the anonymous shell's own entry).
s/category_pages::export\(\$categoryid\), true\);/category_pages::export(\$categoryid));/;
