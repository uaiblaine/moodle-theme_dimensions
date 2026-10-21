# Let the bar take the header actions on a page that opens ordinary too: the page header draws them as well,
# and the button is on the page twice.
s/if \(!\$this->active\) \{\n([ \t]*)return \[\];/if (false) {\n$1return [];/;
