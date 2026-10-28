# Turn the visitor's poster links into buttons whatever the option says: no address for another tab.
s/    if \(links\) \{\n        attributes\.role = 'button';/    if (links && false) {\n        attributes.role = 'button';/;
