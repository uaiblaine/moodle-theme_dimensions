# Never mark the nav: every entry is in the menu and every control stays in the navbar too.
s/nav\.classList\.add\('dimensions-phonenav-' \+ entry\.dataset\.dimensionsPhone\);/void nav;/;
