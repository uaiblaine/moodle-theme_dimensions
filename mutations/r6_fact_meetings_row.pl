# Drop the in-person meetings row from the card.
s/if \(\$this->values->meetings !== null && isset/if (false && isset/;
