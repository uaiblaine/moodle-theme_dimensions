# Serve a hotsite badge whatever it is: a same-origin .html written through the file API is then
# script for everyone who may see the page, and the summary and hero areas beside it still
# refuse the very same bytes.
s/return \$badge->is_valid_image\(\) \? \$badge : null;/return \$badge;/;
