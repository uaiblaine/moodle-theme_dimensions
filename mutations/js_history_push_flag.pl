# Push whatever the caller asked: the remembered address written out at load becomes a step of Back.
s/    if \(push\) \{\n        window\.history\.pushState/    if (true) {\n        window.history.pushState/;
