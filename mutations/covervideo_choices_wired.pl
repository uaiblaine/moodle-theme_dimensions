# The settings page offers core's sizes alone again, so a small-limit site never stores the cover video default.
s/branding::covervideo_choices\(\n\s+(get_max_upload_sizes\(\(int\) \(\$CFG->maxbytes \?\? 0\), 0, 0, branding::covervideo_maxbytes\(\)\))\n\s+\)/$1/;
