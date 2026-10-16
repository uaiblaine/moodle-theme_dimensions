# Give the focus back to nothing as the search closes: it falls to the top of the document.
s/quietly\(\[from, field, parts\.opener\]\.find\(\(control\) => shown\(control\)\) \|\| null\);/quietly(null);/;
