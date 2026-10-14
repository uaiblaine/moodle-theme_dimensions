# A single activity course lands on its main activity's view page, not its other pages.
s/        return \$page->url->compare\(\$main->url, URL_MATCH_BASE\);/        return true;/;
