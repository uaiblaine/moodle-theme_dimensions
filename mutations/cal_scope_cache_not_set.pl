# Set core's MODE_SESSION calendar_categories cache: the read-only range writes $SESSION.
s/(\$categories = array_keys\(\$found\);)/$1 \$cache->set('site', \$categories);/;
