# A new opening no longer starts the re-placing again.
s/touched\.current = false;\n(\s*)\} else if/touched.current = touched.current;\n$1} else if/;
