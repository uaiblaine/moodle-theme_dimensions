# Paint the component's scope cells from an answer: pills created, moved and hidden under React.
s/(\.forEach\(\(fieldset\) => \{\n)        \/\/ The component's scope is told by tellAnswer\(\)[^\n]*\n        if \(drawnByLine\(fieldset\)\) \{\n            return;\n        \}\n/$1/;
