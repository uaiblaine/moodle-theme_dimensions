# Arm the visitor's live listing exactly when its web service is closed. The page then loads a module
# whose every request is refused, and never loads it where the site opened the service.
s/if \(publicaccess::client_enabled\(\)\) \{/if (!publicaccess::client_enabled()) {/;
