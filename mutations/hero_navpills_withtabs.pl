# Draw the row whatever the shell asks: the anonymous shell's flag is ignored.
s/if \(!\$this->withtabs \|\| !\$page->has_secondary_navigation\(\)\) \{/if (!\$page->has_secondary_navigation()) {/;
