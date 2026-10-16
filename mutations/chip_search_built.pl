# Build no chip for the search: a term left in force while the field is folded away shows nowhere.
s/if \(\$this->criteria->search !== ''\) \{/if (false) {/;
