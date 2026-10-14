# Never show the card's own text: every card falls back to the description.
s/if \(trim\(\$cardsummary\) !== ''\) \{/if (false) {/;
