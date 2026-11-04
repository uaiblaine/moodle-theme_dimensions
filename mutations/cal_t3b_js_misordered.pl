# Read the edges before righting dates out of order, so the bar and the connectors disagree.
s/    if \(start !== null && end !== null && end < start\) \{\n        end = start;\n    \}\n//;
