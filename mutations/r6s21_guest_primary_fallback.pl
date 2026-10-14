# Never promote guest access to the primary action.
s/if \(!\$primaryassigned && !\$loginprimary\) \{/if (false) {/
