# Take the first measure inside the first commit again, observer or not: it forces the page's first full layout.
s/if \(typeof ResizeObserver === 'undefined'\) \{\n\s*measureFirst\(\);\n(\s*)\} else if \(/measureFirst();\n$1if (/;
