# State every filter field on a card, whatever the reader may see: a visitor's card names the hidden one.
s/if \(\$printed \|\| \$handler->can_view\(\$field, \(int\) \$this->course->id\)\) \{/if (true) {/;
