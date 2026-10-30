# Hand the shell the answer after the posters' component heard it: the component opens the one area holding every
# result inside the dispatch, and the account's note of it reads the previous answer's sentence (none at first).
s/    shell\.answered\(response\);\n(    document\.dispatchEvent\(new CustomEvent\(LISTING\.criteria, .*?\}\}\)\);\n)/$1    shell.answered(response);\n/s;
