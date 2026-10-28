# Listen for the component's dropterm only after the line is named: a component already imported can
# draw and press a suggestion before anybody drops the term.
s/^    line\.addEventListener\(LINE\.dropterm, \(\) => dropTerm\(\)\);\n//m;
s/^(    line\.dataset\.reactProps = JSON\.stringify\(\{form: form\.id\}\);\n)/$1    line.addEventListener(LINE.dropterm, () => dropTerm());\n/m;
