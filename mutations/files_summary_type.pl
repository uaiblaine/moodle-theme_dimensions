# Serve whatever the summary area holds: an editor-left .html then runs as same-origin script
# for every visitor of the public page.
s/return \(\$file && \$file->is_valid_image\(\)\) \? \$file : null;/return \$file;/;
