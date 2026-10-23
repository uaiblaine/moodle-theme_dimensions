# Drop the excerpt's own require of lib/filelib.php: a request that has not loaded it dies in
# file_rewrite_pluginfile_urls() with "Call to undefined function". The /m flag is what lets ^ and $
# meet a line: the harness runs perl -0777, which reads the whole file as one string.
s/^[ \t]*require_once\(\$CFG->libdir \. '\/filelib\.php'\);[ \t]*\n//m;
