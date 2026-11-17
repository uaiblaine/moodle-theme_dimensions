# Keep the arming's token after the first pass: Behat waits on it for ever.
s/first = false;\n                pending\.resolve\(\);/first = false;/;
