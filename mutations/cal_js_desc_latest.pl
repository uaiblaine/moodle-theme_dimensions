# Show whichever description answers, the latest popover's or not.
s/                if \(turn === ticket\.current\) \{\n                    setText\(html\);/                if (true) {\n                    setText(html);/;
