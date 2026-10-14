# Drop the rule that lets the hidden attribute hide a box's clearing control: its own display wins,
# and every empty box draws a control that clears nothing.
s/\n[ \t]+&\[hidden\] \{\n[ \t]+display: none;\n[ \t]+\}\n/\n/;
