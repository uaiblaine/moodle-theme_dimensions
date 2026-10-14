# Skip the plugin's course term: an unlisted course is treated as listed and shown to strangers,
# whatever the plugin would have answered about it.
s/if \(\(int\) \$row->liststate === discoverability::STATE_UNLISTED\) \{/if (false) {/;
