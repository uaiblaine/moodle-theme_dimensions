# Let the line wrap while it is measured: nothing ever overflows, so the first step always wins.
s/(\.dim-line--measuring \{\n        flex-wrap: )nowrap;/${1}wrap;/;
