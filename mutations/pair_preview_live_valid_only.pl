# Paint the preview from the raw field value, outside the branch the swatch takes for a hex that
# parsed. The band then follows every keystroke: it goes transparent while the "#" is being typed
# and black on a half-written value, on the one element whose job is to show the pair as it will
# be published.
s/        const full = toSixDigit\(colour\);\n        if \(full\) \{\n            colourinput\.value = full;\n            paintPreview\(property, full\);\n        \}/        const full = toSixDigit(colour);\n        paintPreview(property, raw);\n        if (full) {\n            colourinput.value = full;\n        }/;
