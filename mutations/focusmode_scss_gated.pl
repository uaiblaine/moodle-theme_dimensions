# Compile the focus mode stylesheet whatever the master switch says: off, the feature still ships its CSS.
s/if \(\\theme_dimensions\\local\\focusmode::master_enabled\(\)\) \{/if (true) {/;
