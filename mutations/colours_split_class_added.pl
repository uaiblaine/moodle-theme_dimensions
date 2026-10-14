# Mount the contrast panel without marking the fieldset as split. Every element is exactly where
# it should be and the stylesheet's grid never applies, so the panel drops below the rows and the
# editor silently loses its second column - on a page where nothing errors and nothing is missing.
s/    container\.appendChild\(aside\);\n    fieldset\.classList\.add\(SPLIT_CLASS\);/    container.appendChild(aside);/;
