# Draw the double ticket on every event, an online one included.
s/if \(!\$this->is_evento\(\) \|\| !\$this->is_hybrid\(\)\) \{/if (!\$this->is_evento()) {/;
