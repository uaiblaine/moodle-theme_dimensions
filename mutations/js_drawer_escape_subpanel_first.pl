# Close the drawer on an Escape a sub-panel already took: one press would go back a level and close the drawer too.
s/if \(event\.key !== 'Escape' \|\| event\.defaultPrevented \|\| /if (event.key !== 'Escape' || /;
