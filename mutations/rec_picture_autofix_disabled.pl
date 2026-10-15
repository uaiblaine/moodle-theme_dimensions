# Count the picture fields as missing while their type is unavailable: core hides a disabled type's
# fields, so the recommendation would warn and offer a fix that collides with the hidden ones, and the
# provisioner would try to create them on every call.
s/if \(!self::type_available\(\$definition\)\) \{/if (false) {/;
