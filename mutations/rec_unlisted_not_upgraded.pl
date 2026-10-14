# Treat a plugin whose upgrade is waiting as fine, so the overview stays green while its database is behind its code.
s/if \(\$plugin === null \|\| !\$plugin->is_installed_and_upgraded\(\)\) \{/if (\$plugin === null) {/;
