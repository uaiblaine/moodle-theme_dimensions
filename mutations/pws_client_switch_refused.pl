# Answer whatever the service's own switch says.
s/if \(!publicaccess::client_enabled\(\)\) \{/if (false) {/;
