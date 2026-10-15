# Draw the double ticket on a hybrid course as well as on a hybrid event.
s/if \(!\$this->is_event\(\) \|\| !\$this->is_hybrid\(\)\) \{/if (!\$this->is_hybrid()) {/;
