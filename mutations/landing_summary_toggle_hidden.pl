# Leave the toggle on screen over a summary with nothing under the cut.
s/root\.classList\.remove\(CUT_CLASS\);\n            toggle\.hidden = true;/root.classList.remove(CUT_CLASS);\n            toggle.hidden = false;/;
