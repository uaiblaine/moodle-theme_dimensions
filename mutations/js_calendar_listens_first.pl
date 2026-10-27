# Append the component's node before listening to it: a component whose module is already imported
# can say it is drawn, or hand over a period, before anybody is there to hear it.
s/^    region\.append\(node\);\n//m;
s/^(    region\.addEventListener\(CALENDAR\.pick,)/    region.append(node);\n$1/m;
