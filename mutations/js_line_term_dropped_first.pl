# Press the control before the term is dropped: the client sends a request with the term, then one without.
s/hooks\.dropTerm\(\);\n    item\.control\.click\(\);/item.control.click();\n    hooks.dropTerm();/;
