# Tell the shell nothing of an answer: the posters keep linking to the criteria the page was loaded with.
s/\n    shell\.answered\(response\);\n(    document\.dispatchEvent\(new CustomEvent\(LISTING\.criteria)/\n$1/;
