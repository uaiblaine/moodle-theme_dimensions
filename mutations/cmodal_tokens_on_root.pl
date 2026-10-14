# Declare the listing's tokens on the listing alone: inside the dialogue, which core/modal appends to the body, every var() is unresolved.
s/    \.dim-card__panel,\n    \.dim-cmodal \{/    .dim-card__panel {/;
