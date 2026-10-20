# Ignore the recorded answer: a course created with a value of its own, or with the empty choice,
# gets a second row for the field (the unique index refuses it).
s/if \(\$DB->record_exists\('customfield_data', \['instanceid' => \$courseid, 'fieldid' => \$fieldid\]\)\) \{/if (false) {/;
