# Measure the scroller again only on its mount, not when the days held grow.
s/        paint\(measure\(element\), 0\);\n    \}, \[days\]\);/        paint(measure(element), 0);\n    }, []);/;
