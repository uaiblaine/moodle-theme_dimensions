# Fall back to "Open" the site's home for an item with no page of its own.
s/    if \(!opens\(item, courses\)\) \{\n        return \{kind: .none.\};\n    \}\n//;
