# Give the panel a painter of its own instead of the flat list's.
s/(            if \(card\) \{\n                )paintDetail\(card, detail\);/${1}card.hidden = false;/;
