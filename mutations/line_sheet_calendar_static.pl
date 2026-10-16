# Leave the calendar a popover inside the sheet: the sheet's own scroll clips it.
s/(\.dim-daterange__pop \{\n            position: )static;/${1}absolute;/;
