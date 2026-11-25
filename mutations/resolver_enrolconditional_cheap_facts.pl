# Skip enrol_conditional's own cheap facts: a shut, switched-off, full or already-held instance is listed.
s/\} else if \(\$plugin->is_self_enrol_available\(\$instance\) !== true\) \{/} else if (false) {/;
