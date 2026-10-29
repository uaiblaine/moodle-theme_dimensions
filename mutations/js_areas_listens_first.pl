# Append the component's node before the client's answer listens: a component already imported asks
# before anybody answers, and its first panel opens into its error.
s/^    areas\.addEventListener\(AREAS\.ask, \(event\) => \{\n        const detail = \(event as CustomEvent\)\.detail;\n        detail\.answer = client\.ask\(detail\.poster, detail\.psort\);\n    \}\);\n//m;
s/^(    areas\.append\(node\);\n)/$1    areas.addEventListener(AREAS.ask, (event) => {\n        const detail = (event as CustomEvent).detail;\n        detail.answer = client.ask(detail.poster, detail.psort);\n    });\n/m;
