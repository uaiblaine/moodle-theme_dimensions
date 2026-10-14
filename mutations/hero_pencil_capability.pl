# Offer the editor's pencil to everyone: a learner then sees a control to a page that refuses them.
s/if \(!\$isroot && has_capability\('moodle\/category:manage', \$context\)\) \{/if (true) {/;
