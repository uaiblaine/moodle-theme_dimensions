# Send the single activity exit to the bare site root: a logged-in reader whose home page is the Dashboard is
# then redirected on to it instead of reaching the site home page.
s/new \\moodle_url\('\/', \['redirect' => 0\]\)/new \\moodle_url('\/')/;
