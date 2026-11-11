# Print a background rule without an image: an address ending in the revision, painted on every page.
s/        if \(trim\(\$filepath, '\/'\) === ''\) \{\n            return '';\n        \}\n//;
