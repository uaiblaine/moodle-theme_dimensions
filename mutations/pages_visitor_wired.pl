# The anonymous shell stops building the strip: a visitor of a public category loses its pages
# while the logged-in page keeps them.
s/new category_listing\(\$category, \$criteria, \$result, \$view, '', category_pages::export\(\$categoryid\)\);/new category_listing(\$category, \$criteria, \$result, \$view, '', null);/;
