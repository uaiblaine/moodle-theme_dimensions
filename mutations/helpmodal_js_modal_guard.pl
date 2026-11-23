# Let an icon inside a modal be swapped: a dialogue opens over the dialogue that holds the help.
s/return trigger && !trigger\.closest\(SELECTORS\.MODAL\) \? trigger : null;/return trigger;/;
