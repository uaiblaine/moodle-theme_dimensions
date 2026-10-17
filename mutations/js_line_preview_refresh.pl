# Repaint the line without refreshing the preview, whose cards the answer replaced.
s/    render\(\);\n    Preview\.refresh\(\);/    render();/;
