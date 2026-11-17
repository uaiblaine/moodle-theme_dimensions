# Read layout at the arming, outside the measuring pass: a forced layout on the page's load path.
s/^    nav\.classList\.add\(CLASSES\.live\);$/    nav.classList.add(CLASSES.live);\n    void nav.offsetWidth;/m;
