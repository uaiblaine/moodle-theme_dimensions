# Close the layer without giving the focus back to the user button: it falls to the document's body.
s/(if \(returnFocus\) \{\n\s+)state\.toggle\.focus\(\);/${1}document.body.focus();/;
