# Draw the double ticket on every event, an online one included.
s/if \(!\$this->is_event\(\) \|\| !\$this->is_hybrid\(\)\) \{/if (!\$this->is_event()) {/;
