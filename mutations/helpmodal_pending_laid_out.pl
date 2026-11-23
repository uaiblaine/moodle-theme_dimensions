# Hide a pending popover with display: the body has no box, so every text measures as fitting.
s/(\.help-popover\.dimensions-help-pending \{\s*)visibility: hidden;/${1}display: none;/;
