# A format with a view page lands on /course/view.php, and nowhere else.
s/            return \$page->url->compare\(new \\core\\url\('\/course\/view.php'\), URL_MATCH_BASE\);/            return true;/;
