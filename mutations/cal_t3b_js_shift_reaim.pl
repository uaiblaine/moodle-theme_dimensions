# Leave a target on its way where a prepended month cut its animation.
s/(        const arrival = arriving\.current;\n        )if \(arrival\) \{\n(            arrival\.target = shiftedTarget)/$1if (false) {\n$2/;
