# Leave the server's bar count at the page's number after an answer.
s/(if \(total && !drawnByLine\(total\)\) \{\n)        total\.textContent = number;\n/$1/;
