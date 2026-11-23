# Let the focus the dialogue gives back show the popover: closing the dialogue opens the long popover again.
s/(if \(returning && e\.target === returning\) \{\s*returning = null;)\s*e\.preventDefault\(\);/$1/;
