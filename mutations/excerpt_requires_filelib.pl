# Drop the excerpt's own require of lib/filelib.php: a request that has not loaded it dies in
# file_rewrite_pluginfile_urls() with "Call to undefined function".
s/^\s*require_once\(\$CFG->libdir \. '\/filelib\.php'\);\s*$//;
