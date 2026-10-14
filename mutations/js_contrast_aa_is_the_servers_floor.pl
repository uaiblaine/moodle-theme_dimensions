# Grade the pair against 3:1 instead of the server's own 4.5:1 floor. The panel then says "Pass"
# over a pair branding::save() refuses, which is the one disagreement the panel exists to prevent.
# No gate here runs JavaScript, so the sweep exercises the PHP assertions in
# tests/local/category/categoryhero_source_test.php - not a browser.
s/const THRESHOLDS = \{aa: 4\.5,/const THRESHOLDS = {aa: 3,/;
