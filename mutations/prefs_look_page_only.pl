# The Look link is added to every user settings navigation, not only the preferences page's.
s/if \(!\$PAGE->has_set_url\(\) \|\| !\$PAGE->url->compare\(\$preferences, URL_MATCH_BASE\)\) \{/if (false) {/;
