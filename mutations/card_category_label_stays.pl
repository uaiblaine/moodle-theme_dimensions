# Remove only the name, leaving the screen-reader label span before it: the card then announces "Course category:" and no value.
s/\(\?:<span class="visually-hidden">\[\^<\]\*<\/span>\\s\*\)\?//;
