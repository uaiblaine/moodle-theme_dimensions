# Ask for the summary with the page instead of when the drawer is shown: every page pays its statements.
s/\n    armSummary\(root\);\n\};/\n    load(root, root.querySelector(SELECTORS.RESUME));\n};/;
