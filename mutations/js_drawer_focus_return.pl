# Go back without giving the focus back to the row that opened the level: it falls to the document's body.
s/(const opener = openers\.pop\(\);\n\s+if \(opener && parent\.contains\(opener\)\) \{\n\s+)opener\.focus\(\);/${1}root.focus();/;
