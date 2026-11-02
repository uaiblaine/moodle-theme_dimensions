# Close the popover on a press inside core's dialogue over it.
s/const over = target instanceof Element && target\.closest\('\[role="dialog"\]'\) !== null;/const over = target instanceof Element \&\& !target;/;
