# Dim nothing of the other posters while a panel is open.
s/(\.dim-vitrine--cards \.dim-areas--open \.dim-poster:not\(\.dim-poster--open\) \.dim-poster__card \{\n\s*)filter: grayscale\(\.85\);/$1filter: none;/;
