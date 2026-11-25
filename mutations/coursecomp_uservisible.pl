# Name the activities core withholds from the viewer.
s/if \(!\$cm \|\| !\$cm->uservisible\) \{\n                continue;\n            \}\n            \$outcomes/if (!\$cm) {\n                continue;\n            }\n            \$outcomes/;
