# Leave every directory card open, whatever the count. The reveal is what keeps a site with a
# hundred top-level categories from painting a hundred cards - and fetching a hundred images,
# since a hidden card's picture is only fetched once it is revealed - on the one page that is
# the site's front door for a visitor.
s/        \$open = array_slice\(\$entries, 0, self::DIRECTORY_SLICE\);/        \$open = \$entries;/;
