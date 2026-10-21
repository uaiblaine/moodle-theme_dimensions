# Withdraw nothing from the page header: the bar's header actions are drawn twice on a page that opens
# focused, a hidden copy that goes stale the moment the visible one is pressed.
s/\$taken = count\(/\$taken = 0 * count(/;
