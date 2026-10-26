# Squeeze a logo Boost Union's maxlogowidth caps: the fixed height stays and nothing keeps the ratio.
s/(\.navbar\.fixed-top \.navbar-brand \.logo \{\n[^}]*?)    object-fit: contain;\n/$1/;
