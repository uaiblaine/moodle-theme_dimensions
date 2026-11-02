# Draw the placeholder on every page: a page without JavaScript shows empty week rows.
s/(\.dim-cal__placeholder \{\n        display: )none;/$1block;/;
