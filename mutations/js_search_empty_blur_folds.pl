# Leave an empty search open when the focus leaves it.
s/(if \(next && line\.contains\(next\) && next\.closest\(HOOKS\.searchpart\)\) \{\n            return;\n        \}\n)        if \(!keepsOpen\(\)\) \{\n            closeSearch\(false\);\n        \}\n/$1/;
