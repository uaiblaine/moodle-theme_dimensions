# Draw the Filters panel open on load: the chips row is what says what narrows, and an open panel
# pushes the listing down on every narrowed page.
s/data-region="dim-filters"\{\{\^hasfiltergroups\}\}/data-region="dim-filters" open{{^hasfiltergroups}}/;
