# The scope's prior-learning count goes back to every node on the page, ignoring the filters.
s/const prior = \[\.\.\.root\.querySelectorAll\(SELECTORS\.NODE\)\]\.filter\(priorPasses\)\.length;/const prior = root.querySelectorAll(SELECTORS.NODE).length;/;
