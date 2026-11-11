# Stop asking for staff standing in the SQL cut's staff list: every course of a plugin-only clamped
# category reaches the listing of a viewer who holds nothing at it.
s/if \(!\$seeshidden && !is_viewing\(\$context\)\) \{/if (false) {/;
