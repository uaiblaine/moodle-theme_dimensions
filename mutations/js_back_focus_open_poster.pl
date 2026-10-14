# Let Back close the panel with the focus rule of a focus inside it alone. A focus on the open poster of
# a zeroed area then stays on a control that has just left the grid, and falls to the document's body
# instead of going to the summary, as Close, Escape and the poster's own button send it. The sweep
# exercises categorypanel_source_test's assertion.
s/closeArea\(panel\.contains\(active\) \|\| \(Boolean\(open\) && open\.contains\(active\)\), false\);/closeArea(panel.contains(active), false);/;
