# Stop listening for a press outside the open menu: it stays open over the page.
s/^    document\.addEventListener\('pointerdown', \(event\) => onPress\(row, event\), true\);\n//m;
