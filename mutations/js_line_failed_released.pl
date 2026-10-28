# Release the line's pending only when the component draws: a line it cannot draw holds every Behat step of the
# page for good, with nothing on screen saying why.
s/^    line\.addEventListener\(LINE\.failed, \(\) => pending\.resolve\(\), \{once: true\}\);\n//m;
