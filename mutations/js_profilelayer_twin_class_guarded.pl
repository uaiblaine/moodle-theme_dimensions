# Write the twin class whatever the layer carries: a layer without the colour mode hides the navbar's only one.
s/(\n\s+)document\.body\.classList\.add\(CLASSES\.MODES\);\n(\s+)\}/${1}void 0;\n$2}\n$2document.body.classList.add(CLASSES.MODES);/;
