# Let the observer's first entry take no measure: the line is never folded until its width changes.
s/\n\s*if \(!live\.current\.measuredOnce\) \{\n\s*flushSync\(\(\) => \(api\.current\.measureFirst as \(width: number\) => void\)\(width\)\);\n\s*return;\n\s*\}//;
