# Append the component's node before the client's answer listens: a component already imported asks
# before anybody answers, and its first panel opens into its error.
s/^    areas\.addEventListener\(AREAS\.ask, \(event\) => \{\n        event\.detail\.answer = client\.ask\(event\.detail\.poster, event\.detail\.psort\);\n    \}\);\n//m;
s/^(    areas\.append\(node\);\n)/$1    areas.addEventListener(AREAS.ask, (event) => {\n        event.detail.answer = client.ask(event.detail.poster, event.detail.psort);\n    });\n/m;
