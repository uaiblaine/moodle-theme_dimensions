# Answer a request carrying a cachekey, which service.php caches publicly for 90 days.
s/if \(optional_param\('cachekey', 0, PARAM_INT\) > 0\) \{/if (false) {/;
