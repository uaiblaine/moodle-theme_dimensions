# Refuse only an id that names no category: an existing one then reaches core's lookup for anybody, and the
# page goes back to answering a person without the capability differently from an unused id.
s/if \(!\$context \|\| !has_capability\('moodle\/category:manage', \$context\)\) \{/if (!\$context) {/;
