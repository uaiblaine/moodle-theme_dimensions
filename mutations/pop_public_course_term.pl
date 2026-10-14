# Invert the anonymous predicate: the statement admits every visible course whose own state is
# NOT public. The page-slice replay then refuses each of them, so the visitor gets an empty page
# with a count of courses they were never allowed to see.
s/AND c\.visible = 1 AND \{\$state\['column'\]\} = :publicstate";/AND c.visible = 1 AND {\$state['column']} <> :publicstate";/g;
