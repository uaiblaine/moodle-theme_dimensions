# Stop intercepting a press on the navigation tree, so the browser follows the address instead of
# fetching. The listing is right again a page load later, which is what makes this quiet: every
# appended page is thrown away, the scroll position with it, and the one control that is a
# filter goes back to behaving like the navigation it replaced. No gate of this
# suite runs JavaScript, so the sweep exercises amd_source_test's assertion, not a browser.
s/            event\.preventDefault\(\);\n            onTree\(branch\);/            onTree(branch);/;
