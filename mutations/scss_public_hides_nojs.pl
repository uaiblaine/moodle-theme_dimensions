# Let the visitor's mark show the no-JavaScript submits again: a visitor sees a "Filter" button that
# reloads the page beside a listing that already moved.
s/&\.dim-public-js \.dim-nojs-only \{\n        display: none;/&.dim-public-js .dim-nojs-only {\n        display: block;/;
