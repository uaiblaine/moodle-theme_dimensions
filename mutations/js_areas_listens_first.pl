# Answer the component's questions only after its node is appended: a component already imported can ask
# before anybody answers, and that panel opens into its error.
s/^    areas\.addEventListener\(AREAS\.ask, \(event\) => \{\n        event\.detail\.answer = ask\(event\.detail\.poster, event\.detail\.psort\);\n    \}\);\n//m;
s/^(    areas\.append\(node\);\n)/$1    areas.addEventListener(AREAS.ask, (event) => {\n        event.detail.answer = ask(event.detail.poster, event.detail.psort);\n    });\n/m;
