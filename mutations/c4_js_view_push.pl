# Replace the address on a view switch, so Back leaves the page.
s/request\(spanOf\(view, selected, firstday\), state, 'push', selected, false\);/request(spanOf(view, selected, firstday), state, 'replace', selected, false);/;
