# Let a replaced grid discard its cards without unobserving them. IntersectionObserver holds its
# targets strongly, so every filter change then leaks the enrolled cards it discarded before they
# were ever asked about. The shared listing hands each discarded card to the module's discard(),
# which is where the unobserve lives. The sweep exercises amd_source_test's assertion, not a browser.
s/\n    if \(observer\) \{\n        observer\.unobserve\(card\);\n    \}//;
