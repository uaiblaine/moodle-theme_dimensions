# Set core's MODE_SESSION calendar_categories cache: the read-only range writes $SESSION.
s/(        return array_keys\(\$found\);)/        \\cache::make('core', 'calendar_categories')->set('site', array_keys(\$found));\n$1/;
