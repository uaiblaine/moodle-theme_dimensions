# Open a menu without closing the others: two menus open over each other.
s/if \(other !== details && other\.open\) \{\n            close\(row, other, false\);/if (other !== details && other.open) {/;
