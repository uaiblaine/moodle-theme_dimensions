# Drop the tag predicate: a tag value in force keeps every plan.
s/const tagged = \[\.\.\.state\.picked\]\.every\([^\n]*;/const tagged = true;/;
