# Write the appended page into the state the address bar is built from. After two appends the
# document holds pages nought to two, and the next view toggle then stamps "page=2" into the query
# string: a link to a document showing page two alone, with the numbered pager hidden and no route
# back. The sweep exercises listing_account_source_test's assertion, not a browser.
s/    lastpage = Number\(response\.page\);\n    if \(!append\) \{\n        state\.page = lastpage;\n    \}/    lastpage = Number(response.page);\n    state.page = lastpage;/;
