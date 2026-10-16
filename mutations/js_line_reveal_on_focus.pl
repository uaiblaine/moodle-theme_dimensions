# Reveal nothing on focus: a keyboard reader tabs onto a half-hidden cell of the scrolling row.
s/^        scroller\.addEventListener\('focusin', .*\n//m;
