# Drop the name-less default submit: pressing Enter in the search box then submits the first submit button of
# the form, the card view toggle, and flips a list-view reader's remembered view.
s/^\s*<button type="submit" class="visually-hidden dim-toolbar__defaultsubmit" tabindex="-1">\{\{submitlabel\}\}<\/button>\n//m;
