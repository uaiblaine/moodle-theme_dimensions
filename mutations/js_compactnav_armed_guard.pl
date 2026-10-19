# Run the module whatever the server said: the setting off, the app and focus mode no longer stop it.
s/if \(!document\.body\.classList\.contains\(CLASSES\.ARMED\)\) \{/if (false) {/;
