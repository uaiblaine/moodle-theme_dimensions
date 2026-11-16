# Leave a sub-view without giving the focus back to the row that opened it.
s/(if \(opener\) \{\n\s+)opener\.focus\(\);/${1}void opener;/;
