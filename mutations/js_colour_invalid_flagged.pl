# Stop re-checking a colour field when the reader LEAVES it. Somebody who types "12484f", tabs
# away and reads the rest of the form is then told nothing at all: the field looks accepted, and
# the refusal arrives at the save with the page to re-read - which is the state this handler
# exists to end. Typing keeps flagging, so every screenshot of the feature still looks right.
s/^\s*input\.addEventListener\('blur', mark\);\n//m;
