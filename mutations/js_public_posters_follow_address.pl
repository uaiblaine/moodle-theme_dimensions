# Stop pointing the posters' links after an answer. A press meant for another tab then loads the
# criteria the page was loaded with, and the filter the visitor just applied is gone with no word said.
s/(AreaPanel\.criteria\(\{accordion: accordion, areas: response\.areas \|\| \{\}\}\);)\n    linkPosters\(\);/$1/;
